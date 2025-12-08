<?php

namespace Wisp\Pagination;

/**
 * Pagination metadata for API responses.
 *
 * Offset mode:
 *   $pagination = Pagination::forOffset (page: 2, limit: 20)
 *      ->withTotal (150);
 *
 * Cursor mode:
 *   $pagination = Pagination::forCursor (cursor: 'abc123', limit: 20)
 *      ->withCursors (next: 'def456', prev: 'xyz789');
 */
class Pagination
{
   private function __construct (
      private string $mode,
      private int $limit,
      private ?int $page = null,
      private ?string $cursor = null,
      private ?int $total = null,
      private ?int $totalPages = null,
      private ?string $nextCursor = null,
      private ?string $prevCursor = null,
      private bool $hasMore = false
   ) {}

   public static function forOffset (int $page, int $limit): self
   {
      return new self (
         mode: 'offset',
         limit: $limit,
         page: max (1, $page)
      );
   }

   public static function forCursor (?string $cursor, int $limit): self
   {
      return new self (
         mode: 'cursor',
         limit: $limit,
         cursor: $cursor
      );
   }

   /**
    * Auto-detect mode from query parameters.
    *
    * ?page=2&limit=20       → offset mode
    * ?cursor=abc&limit=20   → cursor mode
    * ?limit=20              → offset mode (page=1)
    */
   public static function fromQuery (array $query, int $defaultLimit = 20): self
   {
      $limit = isset ($query ['limit']) ? (int) $query ['limit'] : $defaultLimit;
      $limit = max (1, min (100, $limit)); // Clamp 1-100

      if (isset ($query ['cursor'])) {
         return self::forCursor ($query ['cursor'], $limit);
      }

      $page = isset ($query ['page']) ? (int) $query ['page'] : 1;
      return self::forOffset ($page, $limit);
   }

   public function withTotal (int $total): self
   {
      $clone = clone $this;
      $clone->total = $total;

      if ($this->mode === 'offset') {
         $clone->totalPages = (int) ceil ($total / $this->limit);
         $clone->hasMore = $this->page < $clone->totalPages;
      }

      return $clone;
   }

   public function withCursors (?string $next = null, ?string $prev = null): self
   {
      $clone = clone $this;
      $clone->nextCursor = $next;
      $clone->prevCursor = $prev;
      $clone->hasMore = $next !== null;
      return $clone;
   }

   public function withHasMore (bool $hasMore): self
   {
      $clone = clone $this;
      $clone->hasMore = $hasMore;
      return $clone;
   }

   public function mode (): string
   {
      return $this->mode;
   }

   public function limit (): int
   {
      return $this->limit;
   }

   public function page (): ?int
   {
      return $this->page;
   }

   public function cursor (): ?string
   {
      return $this->cursor;
   }

   public function total (): ?int
   {
      return $this->total;
   }

   public function totalPages (): ?int
   {
      return $this->totalPages;
   }

   public function nextCursor (): ?string
   {
      return $this->nextCursor;
   }

   public function prevCursor (): ?string
   {
      return $this->prevCursor;
   }

   public function hasMore (): bool
   {
      return $this->hasMore;
   }

   /**
    * Get SQL offset for offset mode.
    */
   public function sqlOffset (): int
   {
      if ($this->mode !== 'offset') {
         return 0;
      }

      return ($this->page - 1) * $this->limit;
   }

   /**
    * Convert to array for envelope integration.
    */
   public function toArray (): array
   {
      $data = [
         'mode' => $this->mode,
         'limit' => $this->limit,
         'has_more' => $this->hasMore
      ];

      if ($this->mode === 'offset') {
         $data ['page'] = $this->page;

         if ($this->total !== null) {
            $data ['total'] = $this->total;
            $data ['total_pages'] = $this->totalPages;
         }
      } else {
         if ($this->cursor !== null) {
            $data ['cursor'] = $this->cursor;
         }

         if ($this->nextCursor !== null) {
            $data ['next_cursor'] = $this->nextCursor;
         }

         if ($this->prevCursor !== null) {
            $data ['prev_cursor'] = $this->prevCursor;
         }
      }

      return $data;
   }
}
