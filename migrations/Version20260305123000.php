<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add n8n callback url to quotes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quotes ADD n8n_callback_url VARCHAR(2048) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE quotes DROP n8n_callback_url');
    }
}
