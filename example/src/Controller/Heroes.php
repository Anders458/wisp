<?php

namespace Wisp\Example\Controller;

use Psr\Log\LoggerInterface;
use Wisp\Example\Request\HeroRequest;
use Wisp\Http\Request;
use Wisp\Http\Response;
use Wisp\Http\ValidationException;

class Heroes
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
      $this->logger->info ('Heroes Controller: {method} {uri}', [
         'method' => $this->request->getMethod (),
         'uri' => $this->request->getRequestUri ()
      ]);
   }

   public function index ()
   {
      return $this->response
         ->status (200)
         ->json ([
            'heroes' => [
               ['id' => 'Hamg', 'name' => 'Mountain King', 'faction' => 'Human', 'type' => 'Strength'],
               ['id' => 'Hpal', 'name' => 'Paladin', 'faction' => 'Human', 'type' => 'Strength'],
               ['id' => 'Hmkg', 'name' => 'Archmage', 'faction' => 'Human', 'type' => 'Intelligence'],
               ['id' => 'Hblm', 'name' => 'Blood Mage', 'faction' => 'Human', 'type' => 'Intelligence'],
               ['id' => 'Obla', 'name' => 'Blademaster', 'faction' => 'Orc', 'type' => 'Agility'],
               ['id' => 'Ofar', 'name' => 'Far Seer', 'faction' => 'Orc', 'type' => 'Intelligence'],
               ['id' => 'Otch', 'name' => 'Tauren Chieftain', 'faction' => 'Orc', 'type' => 'Strength'],
               ['id' => 'Oshd', 'name' => 'Shadow Hunter', 'faction' => 'Orc', 'type' => 'Intelligence'],
               ['id' => 'Edem', 'name' => 'Demon Hunter', 'faction' => 'Night Elf', 'type' => 'Agility'],
               ['id' => 'Ekee', 'name' => 'Keeper of the Grove', 'faction' => 'Night Elf', 'type' => 'Intelligence'],
               ['id' => 'Emoo', 'name' => 'Priestess of the Moon', 'faction' => 'Night Elf', 'type' => 'Agility'],
               ['id' => 'Ewar', 'name' => 'Warden', 'faction' => 'Night Elf', 'type' => 'Agility'],
               ['id' => 'Udea', 'name' => 'Death Knight', 'faction' => 'Undead', 'type' => 'Strength'],
               ['id' => 'Ulic', 'name' => 'Lich', 'faction' => 'Undead', 'type' => 'Intelligence'],
               ['id' => 'Udre', 'name' => 'Dreadlord', 'faction' => 'Undead', 'type' => 'Strength'],
               ['id' => 'Ucrl', 'name' => 'Crypt Lord', 'faction' => 'Undead', 'type' => 'Strength']
            ],
            'total' => 16
         ]);
   }

   public function show (string $id)
   {
      $heroes = [
         'Hamg' => ['id' => 'Hamg', 'name' => 'Mountain King', 'faction' => 'Human', 'type' => 'Strength', 'abilities' => ['Storm Bolt', 'Thunder Clap', 'Bash', 'Avatar']],
         'Obla' => ['id' => 'Obla', 'name' => 'Blademaster', 'faction' => 'Orc', 'type' => 'Agility', 'abilities' => ['Wind Walk', 'Mirror Image', 'Critical Strike', 'Bladestorm']],
         'Edem' => ['id' => 'Edem', 'name' => 'Demon Hunter', 'faction' => 'Night Elf', 'type' => 'Agility', 'abilities' => ['Mana Burn', 'Immolation', 'Evasion', 'Metamorphosis']],
         'Udea' => ['id' => 'Udea', 'name' => 'Death Knight', 'faction' => 'Undead', 'type' => 'Strength', 'abilities' => ['Death Coil', 'Death Pact', 'Unholy Aura', 'Animate Dead']]
      ];

      if (!isset ($heroes [$id])) {
         return $this->response
            ->status (404)
            ->json ([ 'error' => 'Hero not found' ]);
      }

      return $this->response
         ->status (200)
         ->json ($heroes [$id]);
   }

   public function stats (string $id)
   {
      $stats = [
         'Hamg' => ['wins' => 1247, 'losses' => 986, 'winrate' => 55.8, 'avg_level' => 5.2, 'pick_rate' => 12.4],
         'Obla' => ['wins' => 1891, 'losses' => 1523, 'winrate' => 55.4, 'avg_level' => 5.7, 'pick_rate' => 18.9],
         'Edem' => ['wins' => 2134, 'losses' => 1687, 'winrate' => 55.9, 'avg_level' => 6.1, 'pick_rate' => 21.2],
         'Udea' => ['wins' => 1654, 'losses' => 1432, 'winrate' => 53.6, 'avg_level' => 5.4, 'pick_rate' => 15.7]
      ];

      if (!isset ($stats [$id])) {
         return $this->response
            ->status (404)
            ->json ([ 'error' => 'Hero stats not found' ]);
      }

      return $this->response
         ->status (200)
         ->json ([
            'hero_id' => $id,
            'statistics' => $stats [$id]
         ]);
   }

   public function store ()
   {
      try {
         $data = $this->request->validate (HeroRequest::class);
      } catch (ValidationException $e) {
         return $e->getResponse ();
      }

      // At this point, $data is a validated HeroRequest instance
      // In a real app, you would save this to a database
      $this->logger->info ('Creating new hero', [
         'name' => $data->name,
         'power' => $data->power,
         'alignment' => $data->alignment
      ]);

      return $this->response
         ->status (201)
         ->json ([
            'message' => 'Hero created successfully',
            'hero' => [
               'name' => $data->name,
               'power' => $data->power,
               'bio' => $data->bio,
               'alignment' => $data->alignment
            ]
         ]);
   }
}
