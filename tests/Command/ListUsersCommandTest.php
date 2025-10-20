<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\ListUsersCommand;
use PHPUnit\Framework\Attributes\DataProvider;

final class ListUsersCommandTest extends AbstractCommandTestCase
{
    /**
     * This test verifies the amount of data is right according to the given parameter max results.
     */
    #[DataProvider('maxResultsProvider')]
    public function testListUsers(int $maxResults): void
    {
        $tester = $this->executeCommand(
            ['--max-results' => $maxResults]
        );

        $emptyDisplayLines = 5;
        $this->assertSame($emptyDisplayLines + $maxResults, mb_substr_count($tester->getDisplay(), "\n"));
    }

    public static function maxResultsProvider(): \Generator
    {
        yield [1];
        yield [2];
    }

    protected function getCommandFqcn(): string
    {
        return ListUsersCommand::class;
    }
}
