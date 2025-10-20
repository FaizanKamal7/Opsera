<?php

namespace App\Command;

use App\Service\ManualScanService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


#[AsCommand(
    name: 'app:manuals:scan',
    description: 'Scans the manuals directory and generates JSON files with the results'
)]
class ManualsScanCommand extends Command
{
    private ManualScanService $scanner;

    /**
     * Constructor
     *
     * @param ManualScanService $scanner Manuals scanner service
     */
    public function __construct(ManualScanService $scanner)
    {
        parent::__construct();
        $this->scanner = $scanner;
    }

    /**
     * Configures the command
     */
    protected function configure(): void
    {
        $this
            ->setDescription('Scans the SVN manuals directory and generates JSON files')
            ->setHelp('This command scans the SVN manuals repository, checks out or updates the working copy, and generates JSON files with the paths and modification dates of PDF files');
    }

    /**
     * Executes the command
     *
     * @param InputInterface $input Command input
     * @param OutputInterface $output Command output
     * @return int Command exit status
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SVN Manuals Scanner');
//        $this->scanner->setOutput($output);

        try {
            $io->section('Starting repository scan...');
            $io->section('Started at ' . date('Y-m-d H:i:s'));
            $result = $this->scanner->scan();

            $io->section('Generating JSON files');
            file_put_contents('manuallist.json', json_encode($result['manuals']));
            file_put_contents('manuallist_shb.json', json_encode($result['safety_guidelines']));

            $io->success([
                'Scan completed successfully!',
                sprintf('Found %d manuals', count($result['manuals'])),
                sprintf('Found %d safety guidelines', count($result['safety_guidelines'])),
            ]);
            $io->section('Ended at ' . date('Y-m-d H:i:s'));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Error scanning manuals:',
                $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }
}
