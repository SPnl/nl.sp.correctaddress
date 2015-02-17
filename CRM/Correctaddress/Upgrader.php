<?php

/**
 * Collection of upgrade steps
 */
class CRM_Correctaddress_Upgrader extends CRM_Correctaddress_Upgrader_Base {

  // By convention, functions that look like "function upgrade_NNNN()" are
  // upgrade tasks. They are executed in order (like Drupal's hook_update_N).

  /**
   * Example: Run an external SQL script when the module is installed
   *
  public function install() {
    $this->executeSqlFile('sql/myinstall.sql');
  }

  /**
   * Example: Run an external SQL script when the module is uninstalled
   *
  public function uninstall() {
   $this->executeSqlFile('sql/myuninstall.sql');
  }

  /**
   * Example: Run a simple query when a module is enabled
   */
  public function enable() {
    CRM_Core_BAO_Setting::setItem('1000', 'Extension', 'nl.sp.correctaddress:version');
  }

  const BATCH_SIZE = 150;

  public function upgrade_1001() {
    $minId = CRM_Core_DAO::singleValueQuery('SELECT min(id) FROM civicrm_address');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT max(id) FROM civicrm_address');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Correct addresses (%1 / %2)', array(
          1 => $startId,
          2 => $maxId,
      ));
      $this->addTask($title, 'correct', $startId, $endId);
    }

    return true;
  }

  public static function correct($startId, $endId) {
    $address = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_address` WHERE `country_id` = 1152 AND `id` BETWEEN %1 AND %2", array(1=>array($startId, 'Integer'), 2=>array($endId,'Integer')), true, 'CRM_Core_DAO_Address');
    while ($address->fetch()) {
      self::correctAddress($address);
    }
    return true;
  }

  protected static function correctAddress($address) {
    $info = civicrm_api3('PostcodeNL', 'get', array('postcode' => $address->postal_code, 'huisnummer' => $address->street_number));
    if (isset($info['values']) && is_array($info['values']) && count($info['values']) > 0) {
      return; //aaddress is found in database
    }

    $sql = "SELECT * FROM `civicrm_postcodenl` WHERE `postcode_letter` = %2 AND `postcode_nr` = %1";
    $postcode = preg_replace('/[^\da-z]/i', '', $address->postal_code);
    $postcodeParams[1] = array(substr($postcode, 0, 4), 'String');
    $postcodeParams[2] = array(substr($postcode, 4, 2), 'String');
    $postcodeDao = CRM_Core_DAO::executeQuery($sql, $postcodeParams);
    while($postcodeDao->fetch()) {
      if (stripos($address->street_address, $postcodeDao->adres)===0 && $postcodeDao->adres != $address->street_name) {
        $housenumber = trim(str_ireplace($postcodeDao->adres, '', $address->street_address));
        if (strlen($address->street_unit)) {
          $housenumber = trim(str_replace($address->street_unit, '', $housenumber));
        }
        if (!is_int($housenumber)) {
          $housenumber = $address->street_number;
        }
        $info = civicrm_api3('PostcodeNL', 'get', array('postcode' => $address->postal_code, 'huisnummer' => $housenumber));
        if (isset($info['values']) && is_array($info['values'])) {
          $params = array();
          $params['street_number'] = $housenumber;
          $params['id'] = $address->id;
          civicrm_api3('Address', 'create', $params);

          $params = array();
          CRM_Core_DAO::storeValues($address, $params);
          $params['street_number'] = $housenumber;
          CRM_Postcodenl_Updater::checkAddress($address->id, $params, true);

          return; //aaddress is found in database
        }
      }
    }

  }

  /**
   * Example: Run a simple query when a module is disabled
   *
  public function disable() {
    CRM_Core_DAO::executeQuery('UPDATE foo SET is_active = 0 WHERE bar = "whiz"');
  }

  /**
   * Example: Run a couple simple queries
   *
   * @return TRUE on success
   * @throws Exception
   *
  public function upgrade_4200() {
    $this->ctx->log->info('Applying update 4200');
    CRM_Core_DAO::executeQuery('UPDATE foo SET bar = "whiz"');
    CRM_Core_DAO::executeQuery('DELETE FROM bang WHERE willy = wonka(2)');
    return TRUE;
  } // */


  /**
   * Example: Run an external SQL script
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4201() {
    $this->ctx->log->info('Applying update 4201');
    // this path is relative to the extension base dir
    $this->executeSqlFile('sql/upgrade_4201.sql');
    return TRUE;
  } // */


  /**
   * Example: Run a slow upgrade process by breaking it up into smaller chunk
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4202() {
    $this->ctx->log->info('Planning update 4202'); // PEAR Log interface

    $this->addTask(ts('Process first step'), 'processPart1', $arg1, $arg2);
    $this->addTask(ts('Process second step'), 'processPart2', $arg3, $arg4);
    $this->addTask(ts('Process second step'), 'processPart3', $arg5);
    return TRUE;
  }
  public function processPart1($arg1, $arg2) { sleep(10); return TRUE; }
  public function processPart2($arg3, $arg4) { sleep(10); return TRUE; }
  public function processPart3($arg5) { sleep(10); return TRUE; }
  // */


  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
  public function upgrade_4203() {
    $this->ctx->log->info('Planning update 4203'); // PEAR Log interface

    $minId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(min(id),0) FROM civicrm_contribution');
    $maxId = CRM_Core_DAO::singleValueQuery('SELECT coalesce(max(id),0) FROM civicrm_contribution');
    for ($startId = $minId; $startId <= $maxId; $startId += self::BATCH_SIZE) {
      $endId = $startId + self::BATCH_SIZE - 1;
      $title = ts('Upgrade Batch (%1 => %2)', array(
        1 => $startId,
        2 => $endId,
      ));
      $sql = '
        UPDATE civicrm_contribution SET foobar = whiz(wonky()+wanker)
        WHERE id BETWEEN %1 and %2
      ';
      $params = array(
        1 => array($startId, 'Integer'),
        2 => array($endId, 'Integer'),
      );
      $this->addTask($title, 'executeSql', $sql, $params);
    }
    return TRUE;
  } // */

}
