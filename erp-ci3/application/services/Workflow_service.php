<?php
/**
 * Workflow engine (Phase 1 foundation): explicit state machine + permission per action + history.
 * Phase 3+ extends with approval thresholds / parallel approvers (tables already support it).
 */
class Workflow_service
{
    private $sm;
    private $repo;
    private $ctx;

    public function __construct(State_machine $sm, Workflow_repository $repo, Request_context $ctx)
    {
        $this->sm = $sm;
        $this->repo = $repo;
        $this->ctx = $ctx;
    }

    /**
     * Validates permission + transition, records action, returns new state. Caller persists document status in same transaction.
     */
    public function transition(string $docType, int $docId, ?string $docNo, string $currentState, string $action, string $permission, ?string $notes = null): string
    {
        if (!$this->ctx->can($permission)) {
            throw new Authorization_exception();
        }
        $next = $this->sm->apply($docType, $currentState, $action);
        $instanceId = $this->repo->ensureInstance($docType, $docId, $docNo, $currentState);
        $this->repo->recordAction($instanceId, $action, $currentState, $next, $this->sm->isFinal($docType, $next), $this->ctx->user_id, $notes);
        return $next;
    }

    public function start(string $docType, int $docId, ?string $docNo): void
    {
        $this->repo->ensureInstance($docType, $docId, $docNo, 'DRAFT');
    }

    public function availableActions(string $docType, string $state): array
    {
        return $this->sm->actions($docType, $state);
    }

    public function history(string $docType, int $docId): array
    {
        return $this->repo->history($docType, $docId);
    }
}
