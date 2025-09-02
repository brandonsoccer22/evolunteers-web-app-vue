<?php

namespace App\Helpers;

use Illuminate\Database\QueryException;

class DatabaseHelper
{
	/**
	 * Determine if the given QueryException was caused by a duplicate entry error.
	 *
	 * @param QueryException $e
	 * @return bool
	 */
	public static function isDuplicateException(QueryException $e): bool
	{
		// 23000 is the SQLSTATE code for integrity constraint violation (includes duplicate entry)
		// 1062 is the MySQL error code for duplicate entry
		return in_array($e->getCode(), [23000,23505, 1062]);
	}
}
