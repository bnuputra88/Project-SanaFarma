<?php
/**
 * Base repository: all data access goes through repositories (no queries in controllers/services/views).
 * Uses CI3 Query Builder (parameterized/escaped). Explicit column lists — no SELECT *.
 */
abstract class Base_repository
{
    protected $table;
    protected $columns = ['*'];
    protected $softDelete = false;
    /** @var CI_DB_query_builder */
    protected $db;

    public function __construct(CI_DB_query_builder $db)
    {
        $this->db = $db;
    }

    public function find(int $id, bool $forUpdate = false): ?array
    {
        $this->db->select($this->columns)->from($this->table)->where('id', $id);
        if ($this->softDelete) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        $sql = $this->db->get_compiled_select() . ($forUpdate ? ' FOR UPDATE' : '');
        $row = $this->db->query($sql)->row_array();
        return $row ?: null;
    }

    public function findOrFail(int $id, bool $forUpdate = false): array
    {
        $row = $this->find($id, $forUpdate);
        if ($row === null) {
            throw new Not_found_exception();
        }
        return $row;
    }

    public function findBy(array $where): ?array
    {
        $row = $this->db->select($this->columns)->from($this->table)->where($where)->limit(1)->get()->row_array();
        return $row ?: null;
    }

    public function insert(array $data): int
    {
        $this->db->insert($this->table, $data);
        return (int) $this->db->insert_id();
    }

    public function insertBatch(array $rows): void
    {
        if ($rows) {
            $this->db->insert_batch($this->table, $rows);
        }
    }

    public function update(int $id, array $data): bool
    {
        $this->db->where('id', $id)->update($this->table, $data);
        return $this->db->affected_rows() >= 0;
    }

    /** Optimistic locking: fails if row version changed. */
    public function updateVersioned(int $id, int $expectedVersion, array $data): void
    {
        $data['version'] = $expectedVersion + 1;
        $this->db->where(['id' => $id, 'version' => $expectedVersion])->update($this->table, $data);
        if ($this->db->affected_rows() !== 1) {
            throw new Conflict_exception();
        }
    }

    public function softDelete(int $id, ?int $userId = null): void
    {
        $this->db->where('id', $id)->update($this->table, ['deleted_at' => date('Y-m-d H:i:s'), 'updated_by' => $userId]);
    }

    public function exists(array $where, ?int $excludeId = null): bool
    {
        $this->db->from($this->table)->where($where);
        if ($excludeId) {
            $this->db->where('id !=', $excludeId);
        }
        return $this->db->count_all_results() > 0;
    }

    /** Generic paginated listing with whitelisted sort columns. */
    protected function paginateQuery(CI_DB_query_builder $qb, array $input, array $sortable, string $defaultSort): Paginator
    {
        [$page, $per, $offset] = Paginator::fromInput($input);
        $countQb = clone $qb;
        $total = (int) $countQb->count_all_results('', false);
        $sort = in_array($input['sort'] ?? '', array_keys($sortable), true) ? $sortable[$input['sort']] : $defaultSort;
        $dir = strtolower($input['dir'] ?? '') === 'desc' ? 'DESC' : (isset($input['dir']) && $input['dir'] !== '' ? 'ASC' : null);
        if (!isset($sortable[$input['sort'] ?? '']) && preg_match('/^(.+?)\s+(asc|desc)$/i', $defaultSort, $m)) {
            $sort = $m[1];
            $dir = strtoupper($m[2]);
        }
        foreach (array_map('trim', explode(',', $sort)) as $col) {
            $qb->order_by($col, $dir ?? 'ASC');
        }
        $rows = $qb->limit($per, $offset)->get()->result_array();
        return new Paginator($rows, $total, $page, $per);
    }

    public function all(array $where = [], string $orderBy = 'id'): array
    {
        $this->db->select($this->columns)->from($this->table);
        if ($where) {
            $this->db->where($where);
        }
        if ($this->softDelete) {
            $this->db->where('deleted_at IS NULL', null, false);
        }
        return $this->db->order_by($orderBy)->get()->result_array();
    }
}
