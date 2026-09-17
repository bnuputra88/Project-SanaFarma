<?php
class Rate_limit_service
{
    private $repo;

    public function __construct(Auth_repository $repo)
    {
        $this->repo = $repo;
    }

    public function hit(string $bucket, int $limitPerMinute): void
    {
        if ($limitPerMinute <= 0) {
            return;
        }
        if ($this->repo->rateHit($bucket, 60) > $limitPerMinute) {
            throw new Rate_limit_exception();
        }
    }
}
