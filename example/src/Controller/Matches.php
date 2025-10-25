<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Matches
{
   public function __construct (
      protected Request $request,
      protected Response $response,
      protected LoggerInterface $logger
   )
   {
   }

   public function before ()
   {
      $this->logger->info ('Matches Controller: {method} {uri}', [
         'method' => $this->request->getMethod (),
         'uri' => $this->request->getRequestUri ()
      ]);
   }

   public function index ()
   {
      $limit = (int) $this->request->query->get ('limit', 10);
      $offset = (int) $this->request->query->get ('offset', 0);

      return $this->response
         ->status (200)
         ->body ([
            'matches' => [
               [
                  'id' => 'match_1729012345',
                  'map' => 'Turtle Rock',
                  'duration' => 1847,
                  'winner' => 'team_1',
                  'players' => ['Grubby', 'Moon', 'FoCuS', 'Lyn'],
                  'created_at' => '2025-10-15 14:32:25'
               ],
               [
                  'id' => 'match_1729011234',
                  'map' => 'Lost Temple',
                  'duration' => 2134,
                  'winner' => 'team_2',
                  'players' => ['TH000', 'Infi', 'Fly100%', 'TeD'],
                  'created_at' => '2025-10-15 13:47:14'
               ],
               [
                  'id' => 'match_1729010123',
                  'map' => 'Twisted Meadows',
                  'duration' => 1523,
                  'winner' => 'team_1',
                  'players' => ['Happy', 'Lawliet', 'Lucifer', 'Hawk'],
                  'created_at' => '2025-10-15 13:15:23'
               ]
            ],
            'pagination' => [
               'limit' => $limit,
               'offset' => $offset,
               'total' => 3
            ]
         ]);
   }

   public function show (string $id)
   {
      $matches = [
         'match_1729012345' => [
            'id' => 'match_1729012345',
            'map' => 'Turtle Rock',
            'mode' => '2v2',
            'duration' => 1847,
            'winner' => 'team_1',
            'teams' => [
               'team_1' => [
                  ['player' => 'Grubby', 'race' => 'Orc', 'hero' => 'Obla', 'apm' => 287, 'level' => 7],
                  ['player' => 'Moon', 'race' => 'Night Elf', 'hero' => 'Edem', 'apm' => 312, 'level' => 6]
               ],
               'team_2' => [
                  ['player' => 'FoCuS', 'race' => 'Undead', 'hero' => 'Udea', 'apm' => 265, 'level' => 6],
                  ['player' => 'Lyn', 'race' => 'Orc', 'hero' => 'Ofar', 'apm' => 241, 'level' => 5]
               ]
            ],
            'created_at' => '2025-10-15 14:32:25'
         ]
      ];

      if (!isset ($matches [$id])) {
         return $this->response
            ->status (404)
            ->body (['error' => 'Match not found']);
      }

      return $this->response
         ->status (200)
         ->body ($matches [$id]);
   }

   public function recent ()
   {
      return $this->response
         ->status (200)
         ->body ([
            'matches' => [
               ['id' => 'match_1729012345', 'map' => 'Turtle Rock', 'duration' => 1847, 'created_at' => '2025-10-15 14:32:25'],
               ['id' => 'match_1729011234', 'map' => 'Lost Temple', 'duration' => 2134, 'created_at' => '2025-10-15 13:47:14'],
               ['id' => 'match_1729010123', 'map' => 'Twisted Meadows', 'duration' => 1523, 'created_at' => '2025-10-15 13:15:23'],
               ['id' => 'match_1729009012', 'map' => 'Echo Isles', 'duration' => 967, 'created_at' => '2025-10-15 12:58:12'],
               ['id' => 'match_1729007901', 'map' => 'Terenas Stand', 'duration' => 1789, 'created_at' => '2025-10-15 12:21:41']
            ]
         ]);
   }
}
