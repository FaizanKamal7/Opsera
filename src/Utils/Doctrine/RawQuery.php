<?php

declare(strict_types=1);

namespace App\Utils\Doctrine;

class RawQuery extends AbstractRawQuery
{
    public function close(): static
    {
        return parent::closeStatement();
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function toIterable(): \Traversable
    {
        $itemNormalizer = $this->getItemNormalizer();
        $result = $this->doExecute($this->getExecuteSql());
        try {
            if (null !== $itemNormalizer) {
                foreach ($result->iterateAssociative() as $item) {
                    $item = $itemNormalizer($item);
                    yield $item;
                }
            } else {
                yield from $result->iterateAssociative();
            }
        } finally {
            $this->close();
        }
    }
}
