<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for interacting with SVN repositories
 */
class SvnService
{
    public function __construct(
        #[Autowire('%env(SVN_REPOSITORY_URL)%')]
        private string $svnUrl,

        #[Autowire('%env(SVN_USERNAME)%')]
        private string $username,

        #[Autowire('%env(SVN_PASSWORD)%')]
        private string $password
    ) {}

    /**
     * Gets directory listing from SVN (remote operation)
     */
    public function listDirectory(string $path = ''): array
    {
        $command = sprintf(
            'svn list --username %s --password %s --non-interactive --trust-server-cert %s/%s',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->svnUrl),
            escapeshellarg($path)
        );

        $output = $this->executeCommand($command);
        return array_filter(array_map('trim', $output));
    }

    /**
     * Gets file contents from SVN (remote operation)
     */
    public function getFileContent(string $path): string
    {
        $command = sprintf(
            'svn cat --username %s --password %s --non-interactive --trust-server-cert %s/%s',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->svnUrl),
            escapeshellarg($path)
        );

        $output = $this->executeCommand($command);
        return implode("\n", $output);
    }

    /**
     * Gets file metadata (including last modified date) from SVN
     */
    public function getFileInfo(string $path): array
    {
        $command = sprintf(
            'svn info --xml --username %s --password %s --non-interactive --trust-server-cert %s/%s',
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->svnUrl),
            escapeshellarg($path)
        );

        $output = $this->executeCommand($command);
        $xml = simplexml_load_string(implode("\n", $output));

        return [
            'date' => (string)$xml->entry->commit->date,
            'size' => (int)$xml->entry->size
        ];
    }

    private function executeCommand(string $command): array
    {
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException(sprintf(
                'SVN command failed with code %d. Command: %s',
                $returnCode,
                str_replace($this->password, '*****', $command)
            ));
        }

        return $output;
    }
}
