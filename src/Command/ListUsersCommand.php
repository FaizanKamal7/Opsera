<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:list-users',
    description: 'Lists all the existing users',
    aliases: ['app:users']
)]
final class ListUsersCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command lists all the users registered in the application:

                  <info>php %command.full_name%</info>

                By default the command only displays the 50 most recent users. Set the number of
                results to display with the <comment>--max-results</comment> option:

                  <info>php %command.full_name%</info> <comment>--max-results=2000</comment>
                HELP
            )
            // commands can optionally define arguments and/or options (mandatory and optional)
            // see https://symfony.com/doc/current/components/console/console_arguments.html
            ->addOption('max-results', null, InputOption::VALUE_OPTIONAL, 'Limits the number of users listed', 50)
        ;
    }

    /**
     * This method is executed after initialize(). It usually contains the logic
     * to execute to complete this command task.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var int|null $maxResults */
        $maxResults = $input->getOption('max-results');

        // Use ->findBy() instead of ->findAll() to allow result sorting and limiting
        $allUsers = $this->users->findBy([], ['id' => 'DESC'], $maxResults);

        $createUserArray = static function (User $user): array {
            return [
                $user->getId(),
                $user->getFullName(),
                $user->getUsername(),
                $user->getActive() ? 'Y' : 'N',
                $user->getEmail(),
                $user->getLanguage(),
                implode(', ', $user->getRoles()),
            ];
        };

        // Doctrine query returns an array of objects, and we need an array of plain arrays
        $usersAsPlainArrays = array_map($createUserArray, $allUsers);

        // In your console commands you should always use the regular output type,
        // which outputs contents directly in the console window. However, this
        // command uses the BufferedOutput type instead, to be able to get the output
        // contents before displaying them. This is useful if you want to use the output
        // for further processing
        $bufferedOutput = new BufferedOutput();
        $io = new SymfonyStyle($input, $bufferedOutput);
        $io->table(
            ['ID', 'Full Name', 'Username', 'Active', 'Email', 'Language',  'Roles'],
            $usersAsPlainArrays
        );

        // instead of just displaying the table of users, store its contents in a variable
        $usersAsATable = $bufferedOutput->fetch();
        $output->write($usersAsATable);

        return Command::SUCCESS;
    }
}
