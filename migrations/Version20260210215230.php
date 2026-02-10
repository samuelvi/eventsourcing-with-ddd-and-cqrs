<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210215230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings ADD status VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE bookings DROP processed_by_n8n');
        $this->addSql('ALTER TABLE quotes RENAME COLUMN menu_id TO product_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings ADD processed_by_n8n BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE bookings DROP status');
        $this->addSql('ALTER TABLE quotes RENAME COLUMN product_id TO menu_id');
    }
}
