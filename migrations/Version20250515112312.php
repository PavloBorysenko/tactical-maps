<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250515112312 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE geo_objects (id INT AUTO_INCREMENT NOT NULL, map_id INT NOT NULL, side_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, ttl INT DEFAULT NULL, icon_url VARCHAR(255) DEFAULT NULL, geometry_type VARCHAR(30) NOT NULL, geometry JSON NOT NULL, visible_to_sides JSON DEFAULT NULL, hash VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_50C41F153C55F64 (map_id), INDEX IDX_50C41F1965D81C4 (side_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE maps (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, center_lat DOUBLE PRECISION NOT NULL, center_lng DOUBLE PRECISION NOT NULL, zoom_level INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE sides (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, color VARCHAR(7) NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_objects ADD CONSTRAINT FK_50C41F153C55F64 FOREIGN KEY (map_id) REFERENCES maps (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_objects ADD CONSTRAINT FK_50C41F1965D81C4 FOREIGN KEY (side_id) REFERENCES sides (id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_objects DROP FOREIGN KEY FK_50C41F153C55F64
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE geo_objects DROP FOREIGN KEY FK_50C41F1965D81C4
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE geo_objects
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE maps
        SQL);
        $this->addSql(<<<'SQL'
            DROP TABLE sides
        SQL);
    }
}
