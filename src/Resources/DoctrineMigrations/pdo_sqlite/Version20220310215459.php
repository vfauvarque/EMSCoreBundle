<?php

declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220310215459 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_41BCBAEC588AB49A');
        $this->addSql('DROP INDEX IDX_41BCBAEC903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__content_type AS SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field FROM content_type');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('CREATE TABLE content_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, field_types_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL COLLATE BINARY, pluralName VARCHAR(100) NOT NULL COLLATE BINARY, singularName VARCHAR(100) NOT NULL COLLATE BINARY, icon VARCHAR(100) DEFAULT NULL COLLATE BINARY, description CLOB DEFAULT NULL COLLATE BINARY, indexTwig CLOB DEFAULT NULL COLLATE BINARY, extra CLOB DEFAULT NULL COLLATE BINARY, lockBy VARCHAR(100) DEFAULT NULL COLLATE BINARY, lockUntil DATETIME DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, business_id_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, deleted BOOLEAN NOT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL COLLATE BINARY, labelField VARCHAR(100) DEFAULT NULL COLLATE BINARY, color_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, userField VARCHAR(100) DEFAULT NULL COLLATE BINARY, dateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, startDateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, endDateField VARCHAR(100) DEFAULT NULL COLLATE BINARY, locationField VARCHAR(100) DEFAULT NULL COLLATE BINARY, referer_field_name VARCHAR(100) DEFAULT NULL COLLATE BINARY, category_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, ouuidField VARCHAR(100) DEFAULT NULL COLLATE BINARY, imageField VARCHAR(100) DEFAULT NULL COLLATE BINARY, videoField VARCHAR(100) DEFAULT NULL COLLATE BINARY, email_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, asset_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, order_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, sort_by VARCHAR(100) DEFAULT NULL COLLATE BINARY, sort_order VARCHAR(4) DEFAULT \'asc\' COLLATE BINARY, create_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, edit_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, view_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, publish_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, delete_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, trash_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, owner_role VARCHAR(100) DEFAULT NULL COLLATE BINARY, orderKey INTEGER NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, web_content BOOLEAN DEFAULT \'1\' NOT NULL, auto_publish BOOLEAN DEFAULT \'0\' NOT NULL, active BOOLEAN NOT NULL, default_value CLOB DEFAULT NULL COLLATE BINARY, translationField VARCHAR(100) DEFAULT NULL COLLATE BINARY, localeField VARCHAR(100) DEFAULT NULL COLLATE BINARY, searchLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL COLLATE BINARY, createLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL COLLATE BINARY, version_tags CLOB DEFAULT NULL COLLATE BINARY --(DC2Type:json_array)
        , version_date_from_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, version_date_to_field VARCHAR(100) DEFAULT NULL COLLATE BINARY, archive_role VARCHAR(100) DEFAULT NULL, CONSTRAINT FK_41BCBAEC588AB49A FOREIGN KEY (field_types_id) REFERENCES field_type (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_41BCBAEC903E3A94 FOREIGN KEY (environment_id) REFERENCES environment (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO content_type (id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field) SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field FROM __temp__content_type');
        $this->addSql('DROP TABLE __temp__content_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf('sqlite' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'sqlite\'.');

        $this->addSql('DROP INDEX UNIQ_41BCBAEC588AB49A');
        $this->addSql('DROP INDEX IDX_41BCBAEC903E3A94');
        $this->addSql('CREATE TEMPORARY TABLE __temp__content_type AS SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field FROM content_type');
        $this->addSql('DROP TABLE content_type');
        $this->addSql('CREATE TABLE content_type (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, field_types_id INTEGER DEFAULT NULL, environment_id INTEGER DEFAULT NULL, created DATETIME NOT NULL, modified DATETIME NOT NULL, name VARCHAR(100) NOT NULL, pluralName VARCHAR(100) NOT NULL, singularName VARCHAR(100) NOT NULL, icon VARCHAR(100) DEFAULT NULL, description CLOB DEFAULT NULL, indexTwig CLOB DEFAULT NULL, extra CLOB DEFAULT NULL, lockBy VARCHAR(100) DEFAULT NULL, lockUntil DATETIME DEFAULT NULL, circles_field VARCHAR(100) DEFAULT NULL, business_id_field VARCHAR(100) DEFAULT NULL, deleted BOOLEAN NOT NULL, ask_for_ouuid BOOLEAN NOT NULL, dirty BOOLEAN NOT NULL, color VARCHAR(50) DEFAULT NULL, labelField VARCHAR(100) DEFAULT NULL, color_field VARCHAR(100) DEFAULT NULL, userField VARCHAR(100) DEFAULT NULL, dateField VARCHAR(100) DEFAULT NULL, startDateField VARCHAR(100) DEFAULT NULL, endDateField VARCHAR(100) DEFAULT NULL, locationField VARCHAR(100) DEFAULT NULL, referer_field_name VARCHAR(100) DEFAULT NULL, category_field VARCHAR(100) DEFAULT NULL, ouuidField VARCHAR(100) DEFAULT NULL, imageField VARCHAR(100) DEFAULT NULL, videoField VARCHAR(100) DEFAULT NULL, email_field VARCHAR(100) DEFAULT NULL, asset_field VARCHAR(100) DEFAULT NULL, order_field VARCHAR(100) DEFAULT NULL, sort_by VARCHAR(100) DEFAULT NULL, sort_order VARCHAR(4) DEFAULT \'asc\', create_role VARCHAR(100) DEFAULT NULL, edit_role VARCHAR(100) DEFAULT NULL, view_role VARCHAR(100) DEFAULT NULL, publish_role VARCHAR(100) DEFAULT NULL, delete_role VARCHAR(100) DEFAULT NULL, trash_role VARCHAR(100) DEFAULT NULL, owner_role VARCHAR(100) DEFAULT NULL, orderKey INTEGER NOT NULL, rootContentType BOOLEAN NOT NULL, edit_twig_with_wysiwyg BOOLEAN NOT NULL, web_content BOOLEAN DEFAULT \'1\' NOT NULL, auto_publish BOOLEAN DEFAULT \'0\' NOT NULL, active BOOLEAN NOT NULL, default_value CLOB DEFAULT NULL, translationField VARCHAR(100) DEFAULT NULL, localeField VARCHAR(100) DEFAULT NULL, searchLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL, createLinkDisplayRole VARCHAR(255) DEFAULT \'ROLE_USER\' NOT NULL, version_tags CLOB DEFAULT NULL --(DC2Type:json_array)
        , version_date_from_field VARCHAR(100) DEFAULT NULL, version_date_to_field VARCHAR(100) DEFAULT NULL)');
        $this->addSql('INSERT INTO content_type (id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field) SELECT id, field_types_id, environment_id, created, modified, name, pluralName, singularName, icon, description, indexTwig, extra, lockBy, lockUntil, circles_field, business_id_field, deleted, ask_for_ouuid, dirty, color, labelField, color_field, userField, dateField, startDateField, endDateField, locationField, referer_field_name, category_field, ouuidField, imageField, videoField, email_field, asset_field, order_field, sort_by, sort_order, create_role, edit_role, view_role, publish_role, delete_role, trash_role, owner_role, orderKey, rootContentType, edit_twig_with_wysiwyg, web_content, auto_publish, active, default_value, translationField, localeField, searchLinkDisplayRole, createLinkDisplayRole, version_tags, version_date_from_field, version_date_to_field FROM __temp__content_type');
        $this->addSql('DROP TABLE __temp__content_type');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_41BCBAEC588AB49A ON content_type (field_types_id)');
        $this->addSql('CREATE INDEX IDX_41BCBAEC903E3A94 ON content_type (environment_id)');
    }
}
