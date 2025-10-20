<?php

declare(strict_types=1);

namespace App\Service\OpenApi;

use App\Service\OpenApi\Attribute\MapReadModel;
use Nelmio\ApiDocBundle\OpenApiPhp\Util;
use Nelmio\ApiDocBundle\RouteDescriber\RouteArgumentDescriber\RouteArgumentDescriberInterface;
use OpenApi\Annotations as OA;
use PHPStan\PhpDocParser\Ast\PhpDoc\ImplementsTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Webmozart\Assert\Assert;

final readonly class RouteArgumentDescriber implements RouteArgumentDescriberInterface
{
    private Lexer $lexer;
    private PhpDocParser $parser;

    public function __construct()
    {
        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);

        $this->lexer = new Lexer($config);
        $this->parser = new PhpDocParser($config, $typeParser, $constExprParser);
    }

    public function describe(ArgumentMetadata $argumentMetadata, OA\Operation $operation): void
    {
        if (!($argumentMetadata->getAttributesOfType(MapReadModel::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null)) {
            return;
        }

        $className = $argumentMetadata->getType();
        if (null === $className || !class_exists($className)) {
            return;
        }

        $ref = new \ReflectionClass($className);

        $docComment = $ref->getDocComment();
        Assert::stringNotEmpty($docComment,
            \sprintf('The "%s" read model is missing the generic data mapping for it\'s items', $className));

        $docNode = $this->parser->parse(new TokenIterator($this->lexer->tokenize($docComment)));

        $dataProvider = array_find($docNode->getImplementsTagValues(), fn (ImplementsTagValueNode $t) => 'ReadDataProviderInterface' === $t->type->type->name);
        Assert::isInstanceOf($dataProvider, ImplementsTagValueNode::class,
            \sprintf('The "%s" read model is missing the @implements tag', $className));
        Assert::count($dataProvider->type->genericTypes, 1,
            \sprintf('The "%s" read model must have exactly one generic item type definition', $className));

        $genericType = $dataProvider->type->genericTypes[0];

        if ($genericType instanceof ArrayShapeNode) {
            $this->describeArrayShapeDataItem($genericType, $operation);
        } elseif ($genericType instanceof IdentifierTypeNode) {
            $this->describeObjectDataItem($genericType, $operation);
        }

        $this->addPaginationFields($operation);
        $this->addQueryExpressionField($operation);
    }

    private function describeArrayShapeDataItem(ArrayShapeNode $node, OA\Operation $operation): void
    {
        foreach ($node->items as $item) {
            if (!$item->keyName instanceof IdentifierTypeNode) {
                continue;
            }
            $name = $item->keyName->name;
            if ($item->valueType instanceof IdentifierTypeNode) {
                $type = $item->valueType->name;
            } elseif ($item->valueType instanceof UnionTypeNode) {
                $types = array_filter($item->valueType->types, fn ($t) => $t instanceof IdentifierTypeNode && 'null' !== $t->name);
                if (array_any($types, fn ($t) => 'string' === $t->name)) {
                    $type = 'string';
                } elseif (array_any($types, fn ($t) => 'bool' === $t->name || 'boolean' === $t->name)) {
                    $type = 'bool';
                } elseif (array_any($types, fn ($t) => 'float' === $t->name || 'double' === $t->name)) {
                    $type = 'float';
                } elseif (array_any($types, fn ($t) => 'int' === $t->name || 'integer' === $t->name)) {
                    $type = 'int';
                } else {
                    continue;
                }
            } else {
                continue;
            }

            $schema = $this->getOperationParameterSchema($name, $operation);

            Util::modifyAnnotationValue($schema, 'nullable', true);

            $defaultFilter = match ($type) {
                'array' => null,
                'string' => \FILTER_DEFAULT,
                'int' => \FILTER_VALIDATE_INT,
                'float' => \FILTER_VALIDATE_FLOAT,
                'bool' => \FILTER_VALIDATE_BOOL,
                default => null,
            };

            $properties = $this->describeValidateFilter($defaultFilter, 0, []);

            foreach ($properties as $key => $value) {
                Util::modifyAnnotationValue($schema, $key, $value);
            }
        }

        foreach ($node->items as $item) {
            if (!$item->keyName instanceof IdentifierTypeNode) {
                continue;
            }
            $name = $item->keyName->name;
            $orderBy = 'order['.$name.']';
            $schema = $this->getOperationParameterSchema($orderBy, $operation);

            Util::modifyAnnotationValue($schema, 'nullable', true);

            $schema->type = 'string';
            $schema->enum = ['asc', 'desc'];
        }
    }

    private function describeObjectDataItem(IdentifierTypeNode $node, OA\Operation $operation): void
    {
        throw new \LogicException('Not implemented yet!');
    }

    private function addPaginationFields(OA\Operation $operation): void
    {
        $schema = $this->getOperationParameterSchema('page', $operation);

        Util::modifyAnnotationValue($schema, 'nullable', true);
        Util::modifyAnnotationValue($schema, 'type', 'integer');

        $schema = $this->getOperationParameterSchema('pageSize', $operation);

        Util::modifyAnnotationValue($schema, 'nullable', true);
        Util::modifyAnnotationValue($schema, 'type', 'integer');
    }

    private function addQueryExpressionField(OA\Operation $operation): void
    {
        $schema = $this->getOperationParameterSchema('query', $operation);

        Util::modifyAnnotationValue($schema, 'nullable', true);
        Util::modifyAnnotationValue($schema, 'type', 'string');
    }

    private function getOperationParameterSchema(string $name, OA\Operation $operation): OA\Schema
    {
        $operationParameter = Util::getOperationParameter($operation, $name, 'query');
        /** @var OA\Schema $schema */
        $schema = Util::getChild($operationParameter, OA\Schema::class);

        return $schema;
    }

    /**
     * @psalm-suppress MixedReturnTypeCoercion,MixedAssignment
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    private function describeValidateFilter(?int $filter, int $flags, array $options): array
    {
        if (null === $filter) {
            return [];
        }

        if (\FILTER_VALIDATE_BOOLEAN === $filter) {
            return ['type' => 'boolean'];
        }

        if (\FILTER_VALIDATE_DOMAIN === $filter) {
            return ['type' => 'string', 'format' => 'hostname'];
        }

        if (\FILTER_VALIDATE_EMAIL === $filter) {
            return ['type' => 'string', 'format' => 'email'];
        }

        if (\FILTER_VALIDATE_FLOAT === $filter) {
            return ['type' => 'number', 'format' => 'float'];
        }

        if (\FILTER_VALIDATE_INT === $filter) {
            $props = [];
            if (\array_key_exists('min_range', $options)) {
                $props['minimum'] = $options['min_range'];
            }

            if (\array_key_exists('max_range', $options)) {
                $props['maximum'] = $options['max_range'];
            }

            return ['type' => 'integer', ...$props];
        }

        if (\FILTER_VALIDATE_IP === $filter) {
            $format = match ($flags) {
                \FILTER_FLAG_IPV4 => 'ipv4',
                \FILTER_FLAG_IPV6 => 'ipv6',
                default => 'ip',
            };

            return ['type' => 'string', 'format' => $format];
        }

        if (\FILTER_VALIDATE_MAC === $filter) {
            return ['type' => 'string', 'format' => 'mac'];
        }

        if (\FILTER_VALIDATE_REGEXP === $filter) {
            return ['type' => 'string', 'pattern' => $options['regexp']];
        }

        if (\FILTER_VALIDATE_URL === $filter) {
            return ['type' => 'string', 'format' => 'uri'];
        }

        if (\FILTER_DEFAULT === $filter) {
            return ['type' => 'string'];
        }

        return [];
    }
}
