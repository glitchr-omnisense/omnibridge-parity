<?php

namespace App\EntitySubscriber;

use App\Entity\Comment;
use Base\Contract\Lifecycle\EntityEvent;
use Base\Contract\Lifecycle\PrePersist;

/**
 * A listener of the neutral lifecycle (omnibase's Base\Contract\Lifecycle events, which omnibridge/doctrine relays
 * from Doctrine's and omnibridge/eloquent dispatches in its own flush): it stamps a comment when it is persisted and
 * keeps the moments it heard of comments, which App\Action\RepositoryLifecycle reads back. No host tags it: an
 * invokable of src/EntitySubscriber is a listener by the layout (omnibridge/symfony's LifecycleListenersPass,
 * omnibridge/laravel's OmnibaseServices), one instance per request.
 */
final class CommentLifecycle
{
    public const STAMP = 'stamped before its insert';

    /** @var list<string> the events heard, by their short name (PrePersist, PostPersist, PostLoad...) */
    private array $heard = [];

    public function __invoke(object $event): void
    {
        if (!$event instanceof EntityEvent || !$event->entity instanceof Comment) {
            return;
        }
        $this->heard[] = (new \ReflectionClass($event))->getShortName();
        if ($event instanceof PrePersist) {
            $event->entity->stamp = self::STAMP;
        }
    }

    /** @return list<string> the events heard since the last call, which forgets them */
    public function heard(): array
    {
        [$heard, $this->heard] = [$this->heard, []];

        return $heard;
    }
}
