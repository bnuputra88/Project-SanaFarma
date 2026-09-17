<?php
/**
 * Explicit document state machine. Transitions come from config/erp.php (configurable, not hard-coded in services).
 * Usage: $sm->apply('stock_adjustment', 'DRAFT', 'submit') => 'SUBMITTED' or throws Invalid_transition_exception.
 */
final class State_machine
{
    private $definitions;

    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
    }

    public function apply(string $docType, string $currentState, string $action): string
    {
        $def = $this->definitions[$docType] ?? null;
        if ($def === null) {
            throw new Invalid_transition_exception("Unknown document type '$docType'");
        }
        $next = $def['transitions'][$currentState][$action] ?? null;
        if ($next === null) {
            throw new Invalid_transition_exception(sprintf("Aksi '%s' tidak diizinkan pada status '%s' (%s)", $action, $currentState, $docType));
        }
        return $next;
    }

    public function can(string $docType, string $currentState, string $action): bool
    {
        return isset($this->definitions[$docType]['transitions'][$currentState][$action]);
    }

    public function actions(string $docType, string $currentState): array
    {
        return array_keys($this->definitions[$docType]['transitions'][$currentState] ?? []);
    }

    public function isFinal(string $docType, string $state): bool
    {
        return in_array($state, $this->definitions[$docType]['final'] ?? [], true);
    }
}
