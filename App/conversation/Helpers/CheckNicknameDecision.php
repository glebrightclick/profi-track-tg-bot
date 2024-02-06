<?php

namespace App\Conversation\Helpers;

class CheckNicknameDecision
{
    private ?string $reason;
    private ?bool $decision;

    public function reason(): ?string
    {
        return $this->reason;
    }

    public function rejected(string $reason): self
    {
        $this->reason = $reason;
        $this->decision = false;
        return $this;
    }

    public function isRejected(): bool
    {
        return $this->decision == false;
    }

    public function confirmed(): self
    {
        $this->reason = null;
        $this->decision = true;
        return $this;
    }
}