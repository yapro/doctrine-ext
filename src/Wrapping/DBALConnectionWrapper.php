<?php
declare(strict_types=1);

namespace YaPro\DoctrineExt\Wrapping;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection;

class DBALConnectionWrapper extends Connection
{
	public const SELECT_FOUND_ROWS = 'SELECT FOUND_ROWS()';

	private string $partsecuteQuerySql = '';
	private array $partsecuteQueryParams = [];

	public function executeQuery($query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): \Doctrine\DBAL\Result
    {
		$this->initExecuteQuerySqlAndParams($query, $params);

		return parent::executeQuery($this->executeQuerySql, $this->executeQueryParams, $types, $qcp);
	}

	public function prepare($statement): \Doctrine\DBAL\Statement
    {
		if ($statement === self::SELECT_FOUND_ROWS) {
			// чтобы автоматически прокидывать SQL-параметры нужно использовать магию, ведь
			// \Doctrine\DBAL\Statement::$params защищена, а вручную прокидывать $stmt->execute($params) неудобно
			throw new \UnexpectedValueException('SELECT_FOUND_ROWS not implemented for prepare, please use one of: ' .
				'fetchAssoc, fetchArray, fetchColumn, fetchAll, project or executeQuery');
		}

		return parent::prepare($statement);
	}

	/**
	 * Последовательность выполнения:
	 *
	 * FROM, включая JOINs
	 * WHERE
	 * GROUP BY
	 * HAVING
	 * Функции WINDOW
	 * SELECT
	 * DISTINCT
	 * UNION
	 * ORDER BY
	 * LIMIT и OFFSET
	 *
	 * @param string $sql
	 * @param array $params
	 * @return void
	 */
	private function initExecuteQuerySqlAndParams(string $sql, array $params = []): void
	{
		// удаляем SQL-комментарии, например "-- my comment" и если этого не сделать, то ниже код удалит переносы строк
		// и SQL-комментарий может закомментировать нужный SQL
		if($sqlWithoutComments = preg_replace('/--(.*)(\r|\n|\r\n)/sUi', ' ', $sql)){
			$sql = $sqlWithoutComments;
		}
		$sql = trim(str_replace(["\n", "\r\n", "\r", "\t"], ' ', $sql));
		$sqlString = mb_strtolower($sql);
		if (mb_substr($sqlString, 0, 6) !== 'select') {
			$this->executeQuerySql = $sql;
			return;
		}

		if (false === mb_strpos($sqlString, ' from ') && $sql !== self::SELECT_FOUND_ROWS) {
			$this->executeQuerySql = $sql;
			return;
		}

		if ($sql !== self::SELECT_FOUND_ROWS) {
			$this->executeQueryParams = $params;
			$this->executeQuerySql = $sql;
			return;
		}
		// заменим все, что между первым SELECT ... и ... FROM на COUNT(*) as found_rows
		$parts = preg_split('/ FROM /i', $this->executeQuerySql);
		unset($parts[0]);
		$lastSql = 'SELECT COUNT(*) as found_rows FROM ' . implode(' FROM ', $parts);

		// удалим последний LIMIT (но только если он не в подзапросе)
		$parts = preg_split('/ LIMIT /i', $lastSql);
		$limitValue = end($parts);
		if (false === mb_strpos($limitValue, ')')) {
			$lastKey = array_key_last($parts);
			unset($parts[$lastKey]);
			$this->executeQuerySql = implode(' LIMIT ', $parts);
			return;
		}

		$this->executeQuerySql = $lastSql;
	}
}
