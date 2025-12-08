<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Wisp\Attribute\Cached;
use Wisp\Attribute\Log;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Pagination\Pagination;

/**
 * Example controller demonstrating Pagination and #[Cached] features.
 */
#[Route ('/v1/products')]
#[Log]
class ProductController
{
   private array $products = [
      [ 'id' => 1, 'name' => 'Widget A', 'price' => 9.99 ],
      [ 'id' => 2, 'name' => 'Widget B', 'price' => 19.99 ],
      [ 'id' => 3, 'name' => 'Gadget X', 'price' => 29.99 ],
      [ 'id' => 4, 'name' => 'Gadget Y', 'price' => 39.99 ],
      [ 'id' => 5, 'name' => 'Device Z', 'price' => 49.99 ],
      [ 'id' => 6, 'name' => 'Tool Alpha', 'price' => 59.99 ],
      [ 'id' => 7, 'name' => 'Tool Beta', 'price' => 69.99 ],
      [ 'id' => 8, 'name' => 'Item One', 'price' => 79.99 ],
      [ 'id' => 9, 'name' => 'Item Two', 'price' => 89.99 ],
      [ 'id' => 10, 'name' => 'Item Three', 'price' => 99.99 ]
   ];

   /**
    * List products with pagination.
    *
    * Offset mode: GET /v1/products?page=2&limit=3
    * Cursor mode: GET /v1/products?cursor=5&limit=3
    */
   #[Route ('', methods: [ 'GET' ])]
   #[Cached (ttl: 60, vary: [ 'Accept' ])]
   public function index (Request $request): Response
   {
      $pagination = $request->paginate (defaultLimit: 5);
      $total = count ($this->products);

      if ($pagination->mode () === 'cursor') {
         // Cursor-based: cursor is the last seen ID
         $cursor = $pagination->cursor ();
         $startIndex = 0;

         if ($cursor !== null) {
            foreach ($this->products as $i => $product) {
               if ((string) $product ['id'] === $cursor) {
                  $startIndex = $i + 1;
                  break;
               }
            }
         }

         $items = array_slice ($this->products, $startIndex, $pagination->limit ());
         $lastItem = end ($items);
         $nextCursor = $lastItem && $startIndex + count ($items) < $total
            ? (string) $lastItem ['id']
            : null;

         $pagination = $pagination->withCursors (next: $nextCursor);
      } else {
         // Offset-based
         $items = array_slice (
            $this->products,
            $pagination->sqlOffset (),
            $pagination->limit ()
         );

         $pagination = $pagination->withTotal ($total);
      }

      return (new Response)->paginated ($items, $pagination);
   }

   /**
    * Get a single product (cached for 5 minutes).
    */
   #[Route ('/{id}', methods: [ 'GET' ], requirements: [ 'id' => '\d+' ])]
   #[Cached (ttl: 300)]
   public function show (int $id): Response
   {
      foreach ($this->products as $product) {
         if ($product ['id'] === $id) {
            return (new Response)->json ($product);
         }
      }

      return (new Response)->status (404)->json ([ 'error' => 'Product not found' ]);
   }

   /**
    * Get featured products (public cache for CDN).
    */
   #[Route ('/featured', methods: [ 'GET' ])]
   #[Cached (ttl: 3600, public: true)]
   public function featured (): Response
   {
      $featured = array_slice ($this->products, 0, 3);
      return (new Response)->json ($featured);
   }

   /**
    * Create product (no caching on mutations).
    */
   #[Route ('', methods: [ 'POST' ])]
   public function store (Request $request): Response
   {
      return (new Response)->status (201)->json ([
         'id' => 11,
         'name' => $request->input ('name'),
         'price' => $request->input ('price')
      ]);
   }
}
