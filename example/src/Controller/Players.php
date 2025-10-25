<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Http\Request;
use Wisp\Http\Response;

class Players
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
      $this->logger->info ('Players Controller: {method} {uri}', [
         'method' => $this->request->getMethod (),
         'uri' => $this->request->getRequestUri ()
      ]);
   }

   public function index ()
   {
      return $this->response
         ->status (200)
         ->body ([
            'players' => [
               ['username' => 'Grubby', 'race' => 'Orc', 'mmr' => 2847, 'rank' => 12],
               ['username' => 'Moon', 'race' => 'Night Elf', 'mmr' => 3124, 'rank' => 3],
               ['username' => 'Happy', 'race' => 'Undead', 'mmr' => 3287, 'rank' => 1],
               ['username' => 'TH000', 'race' => 'Human', 'mmr' => 3156, 'rank' => 2],
               ['username' => 'Lyn', 'race' => 'Orc', 'mmr' => 2934, 'rank' => 7]
            ]
         ]);
   }

   public function show (string $username)
   {
      $players = [
         'Grubby' => [
            'username' => 'Grubby',
            'race' => 'Orc',
            'mmr' => 2847,
            'rank' => 12,
            'wins' => 1247,
            'losses' => 986,
            'winrate' => 55.8,
            'favorite_hero' => 'Obla',
            'avg_apm' => 287,
            'profile' => [
               'games_played' => 2233,
               'total_playtime' => 1247890,
               'longest_match' => 4567,
               'avg_match_length' => 1834
            ]
         ],
         'Moon' => [
            'username' => 'Moon',
            'race' => 'Night Elf',
            'mmr' => 3124,
            'rank' => 3,
            'wins' => 2134,
            'losses' => 1687,
            'winrate' => 55.9,
            'favorite_hero' => 'Edem',
            'avg_apm' => 312,
            'profile' => [
               'games_played' => 3821,
               'total_playtime' => 2134567,
               'longest_match' => 5234,
               'avg_match_length' => 1923
            ]
         ]
      ];

      if (!isset ($players [$username])) {
         return $this->response
            ->status (404)
            ->body (['error' => 'Player not found']);
      }

      return $this->response
         ->status (200)
         ->body ($players [$username]);
   }

   public function matches (string $username)
   {
      return $this->response
         ->status (200)
         ->body ([
            'username' => $username,
            'matches' => [
               ['id' => 'match_1729012345', 'result' => 'win', 'hero' => 'Obla', 'duration' => 1847],
               ['id' => 'match_1729011234', 'result' => 'loss', 'hero' => 'Ofar', 'duration' => 2134],
               ['id' => 'match_1729010123', 'result' => 'win', 'hero' => 'Obla', 'duration' => 1523]
            ]
         ]);
   }

   public function rankings ()
   {
      return $this->response
         ->status (200)
         ->body ([
            'leaderboard' => [
               ['rank' => 1, 'username' => 'Happy', 'race' => 'Undead', 'mmr' => 3287, 'wins' => 1654, 'losses' => 1432],
               ['rank' => 2, 'username' => 'TH000', 'race' => 'Human', 'mmr' => 3156, 'wins' => 1523, 'losses' => 1387],
               ['rank' => 3, 'username' => 'Moon', 'race' => 'Night Elf', 'mmr' => 3124, 'wins' => 2134, 'losses' => 1687],
               ['rank' => 4, 'username' => 'Infi', 'race' => 'Human', 'mmr' => 3089, 'wins' => 1834, 'losses' => 1534],
               ['rank' => 5, 'username' => 'Fly100%', 'race' => 'Orc', 'mmr' => 2987, 'wins' => 1456, 'losses' => 1289]
            ],
            'updated_at' => '2025-10-15 15:00:00'
         ]);
   }
}
