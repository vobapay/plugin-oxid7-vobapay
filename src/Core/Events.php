<?php

namespace Vobapay\Payment\Core;

use OxidEsales\Eshop\Core\DatabaseProvider;
use Vobapay\Payment\Model\PaymentConfig;
use Vobapay\Payment\Helper\Config;

/**
 * Class Events
 * Handles module activation and deactivation events.
 */
class Events
{
    /**
     * Installs payment methods and adds necessary columns to the oxorder table.
     *
     */
    public static function onActivate()
    {
        self::installPayments();

        // CREATE NEW TABLE
        self::addTableIfNotExists(PaymentConfig::$sTableName, PaymentConfig::getTableCreateQuery());

        self::addColumnToTable('oxorder', 'VOBAPAYSTATUS', 'TINYINT(1) NULL');
        self::addColumnToTable('oxorder', 'VOBAPAYDELCOSTREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYPAYCOSTREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYWRAPCOSTREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYVOUCHERDISCOUNTREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYDISCOUNTREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYGIFTCARDREFUNDED', "DOUBLE NOT NULL DEFAULT '0'");
        self::addColumnToTable('oxorder', 'VOBAPAYEXTERNALTRANSID', "VARCHAR(64) CHARSET utf8 COLLATE utf8_general_ci DEFAULT '' NOT NULL;");

        self::addColumnToTable('oxorderarticles', 'VOBAPAYQUANTITYREFUNDED', "INT(11) NOT NULL DEFAULT '0';");
        self::addColumnToTable('oxorderarticles', 'VOBAPAYAMOUNTREFUNDED', "DOUBLE NOT NULL DEFAULT '0';");

        self::regenerateViews();
        self::clearTmp();
    }

    /**
     * Installs payment methods defined in the Config class into the oxpayments table.
     * Checks if each payment method already exists before attempting to insert.
     */
    protected static function installPayments()
    {
        $db = DatabaseProvider::getDb();

        foreach (Config::getMethodsList() as $id => $pm) {
            $name_de = $pm["name"]["de"];
            $name_en = $pm["name"]["en"];
            $desc_de = $pm["description"]["de"];
            $desc_en = $pm["description"]["en"];

            $sCheckQuery = "SELECT 1 FROM oxpayments WHERE oxid = " . $db->quote($id);

            if (!$db->getOne($sCheckQuery)) {
                $insert = "INSERT INTO oxpayments (
                    oxid, oxactive, oxdesc, oxaddsum, oxaddsumtype, oxaddsumrules, oxfromboni, oxfromamount,
                oxtoamount, oxvaldesc, oxchecked, oxdesc_1, oxvaldesc_1, oxdesc_2, oxvaldesc_2, oxdesc_3,
                oxvaldesc_3, oxlongdesc, oxlongdesc_1, oxlongdesc_2, oxlongdesc_3, oxsort
                ) VALUES (
                    " . $db->quote($id) . ", 0, " . $db->quote($name_de) . ", 0, 'abs', 15, 0, 0,
                     1000000, '', 0, " . $db->quote($name_en) . ", '', '', '', '', '',
                    " . $db->quote($desc_de) . ", " . $db->quote($desc_en) . ", '', '', 0
                )";
                $db->execute($insert);
            } else {
                // Activate the payment method if it already exists
                $update = "UPDATE oxpayments SET oxactive = 1 WHERE oxid = " . $db->quote($id);
                $db->execute($update);
            }
        }
    }

    /**
     * Add a database table.
     *
     * @param string $sTableName table to add
     * @param string $sQuery sql-query to add table
     *
     * @return boolean true or false
     */
    protected static function addTableIfNotExists($sTableName, $sQuery)
    {
        $aTables = DatabaseProvider::getDb()->getAll("SHOW TABLES LIKE ?", array($sTableName));
        if (!$aTables || count($aTables) == 0) {
            DatabaseProvider::getDb()->Execute($sQuery);
            return true;
        }
        return false;
    }

    /**
     * Adds a column to the selected table if it doesn't exist.
     *
     * @param string $sTableName Name of the table.
     * @param string $sColumnName Name of the column to add.
     * @param string $sColumnDefinition SQL definition of the column.
     */
    protected static function addColumnToTable($sTableName, $sColumnName, $sColumnDefinition)
    {
        $db = DatabaseProvider::getDb();

        // Check if the table exists
        $sCheckTableQuery = "SHOW TABLES LIKE " . $db->quote($sTableName);
        if (!$db->getOne($sCheckTableQuery)) {
            return;
        }

        // Check if the column exists
        $sCheckColumnQuery = "SHOW COLUMNS FROM `{$sTableName}` LIKE " . $db->quote($sColumnName);
        if (!$db->getOne($sCheckColumnQuery)) {
            $sql = "ALTER TABLE `{$sTableName}` ADD `{$sColumnName}` {$sColumnDefinition}";
            $db->execute($sql);
        }
    }

    /**
     * Regenerates database view-tables.
     *
     * @return void
     */
    protected static function regenerateViews()
    {
        $oShop = oxNew('oxShop');
        $oShop->generateViews();
    }

    /**
     * Clear cache.
     *
     * @return void
     */
    protected static function clearTmp()
    {
        $output = shell_exec(VENDOR_PATH . '/bin/oe-console oe:cache:clear');
    }

    /**
     * Deactivates the payment methods associated with the module.
     *
     */
    public static function onDeactivate()
    {
        $paymentMethods = array_keys(Config::getMethodsList());
        $db = DatabaseProvider::getDb();
        $placeholders = implode(',', array_fill(0, count($paymentMethods), '?'));
        $query = "UPDATE oxpayments SET oxactive = 0 WHERE oxid IN ($placeholders)";
        $db->execute($query, $paymentMethods);
    }
}
