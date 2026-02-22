<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215144058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bookings (id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, data JSON NOT NULL, status VARCHAR(50) NOT NULL, user_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7A853C35A76ED395 ON bookings (user_id)');
        $this->addSql('CREATE TABLE menus (id UUID NOT NULL, title VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE products (id UUID NOT NULL, name VARCHAR(255) NOT NULL, type VARCHAR(50) NOT NULL, price DOUBLE PRECISION NOT NULL, currency VARCHAR(3) NOT NULL, external_reference_id UUID DEFAULT NULL, supplier_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_B3BA5A5A2ADD6D8C ON products (supplier_id)');
        $this->addSql('CREATE TABLE quotes (id UUID NOT NULL, booking_id UUID NOT NULL, supplier_id UUID NOT NULL, product_id UUID NOT NULL, status VARCHAR(50) NOT NULL, price DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE suppliers (id UUID NOT NULL, name VARCHAR(255) NOT NULL, is_active BOOLEAN NOT NULL, rating DOUBLE PRECISION NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, address VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('ALTER TABLE bookings ADD CONSTRAINT FK_7A853C35A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT FK_B3BA5A5A2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES suppliers (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings DROP CONSTRAINT FK_7A853C35A76ED395');
        $this->addSql('ALTER TABLE products DROP CONSTRAINT FK_B3BA5A5A2ADD6D8C');
        $this->addSql('DROP TABLE bookings');
        $this->addSql('DROP TABLE menus');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE quotes');
        $this->addSql('DROP TABLE suppliers');
        $this->addSql('DROP TABLE users');
    }
}
