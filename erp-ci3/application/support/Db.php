<?php
/**
 * Thin wrapper over CI_DB providing named transactions with automatic rollback on exception.
 * All critical business operations MUST run through Db::transaction().
 */
final class Db
{
    /** @var CI_DB_query_builder */
    public $ci;

    public function __construct(CI_DB_query_builder $db)
    {
        $this->ci = $db;
    }

    /**
     * Runs callable inside a transaction. Nested calls join the outer transaction (CI3 trans_depth).
     * @return mixed value returned by callable
     * @throws Throwable original exception after rollback
     */
    public function transaction(callable $fn)
    {
        $this->ci->trans_begin();
        try {
            $result = $fn($this->ci);
            if ($this->ci->trans_status() === false) {
                throw new Persistence_exception($this->ci->error()['message'] ?? 'Database error during transaction');
            }
            $this->ci->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->ci->trans_rollback();
            throw $e;
        }
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
