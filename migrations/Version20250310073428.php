<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250310073428 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE refresh_tokens (
          refresh_token VARCHAR(128) NOT NULL,
          username VARCHAR(255) NOT NULL,
          valid DATETIME NOT NULL,
          id INT AUTO_INCREMENT NOT NULL,
          UNIQUE INDEX UNIQ_9BACE7E1C74F2195 (refresh_token),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8');
        $this->addSql('CREATE TABLE users (
          id BIGINT AUTO_INCREMENT NOT NULL,
          username VARCHAR(50) NOT NULL,
          password VARCHAR(255) NOT NULL,
          active TINYINT(1) NOT NULL,
          language VARCHAR(3) NOT NULL,
          roles JSON NOT NULL,
          full_name VARCHAR(255) NOT NULL,
          email VARCHAR(255) NOT NULL,
          UNIQUE INDEX UNIQ_1483A5E9F85E0677 (username),
          UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email),
          PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE users');
    }
}
