<?php

namespace Oro\Bundle\PlatformBundle\Migrations\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;

use Oro\Bundle\MigrationBundle\Migration\Extension\DatabasePlatformAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroPlatformBundleInstaller implements Installation, DatabasePlatformAwareInterface
{
    /** @var AbstractPlatform */
    protected $platform;

    /**
     * {@inheritdoc}
     */
    public function getMigrationVersion()
    {
        return 'v1_0';
    }

    /**
     * {@inheritdoc}
     */
    public function setDatabasePlatform(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    /**
     * {@inheritdoc}
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        /** Tables generation **/
        $this->oroSessionTable($schema, $queries);
    }

    /**
     * Makes sure oro_session table is up-to-date
     *
     * @param Schema   $schema
     * @param QueryBag $queries
     */
    public function oroSessionTable(Schema $schema, QueryBag $queries)
    {
        if (!$schema->hasTable('oro_session')) {
            $this->createOroSessionTable($schema);
        } else {
            $currentSchema  = new Schema([clone $schema->getTable('oro_session')]);
            $requiredSchema = new Schema();
            $this->createOroSessionTable($requiredSchema);

            $comparator = new Comparator();
            $changes    = $comparator->compare($currentSchema, $requiredSchema)->toSql($this->platform);
            if ($changes) {
                // force data purging as a result of dropTable/createTable pair
                // might be "ALTER TABLE" query rather than "DROP/CREATE" queries
                $queries->addPreQuery('DELETE FROM oro_session');
                // recreate oro_session table
                $schema->dropTable('oro_session');
                $this->createOroSessionTable($schema);
            }
        }
    }

    /**
     * Create oro_session table
     *
     * @param Schema $schema
     */
    public function createOroSessionTable(Schema $schema)
    {
        $table = $schema->createTable('oro_session');
        if ($this->platform instanceof MySqlPlatform) {
            $table->addColumn('id', Type::BINARY, ['length' => 128]);
            $table->addColumn('sess_data', Type::BLOB, ['length' => MySqlPlatform::LENGTH_LIMIT_BLOB]);
        } else {
            $table->addColumn('id', Type::STRING, ['length' => 128]);
            $table->addColumn('sess_data', Type::BLOB, []);
        }
        $table->addColumn('sess_time', Type::INTEGER, []);
        $table->addColumn('sess_lifetime', Type::INTEGER, []);
        $table->setPrimaryKey(['id']);
    }
}
