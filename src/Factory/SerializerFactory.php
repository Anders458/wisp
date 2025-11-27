<?php

namespace Wisp\Factory;

use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

class SerializerFactory
{
   public static function create () : SerializerInterface
   {
      return new Serializer (
         [
            new DateTimeNormalizer (),
            new ArrayDenormalizer (),
            new ObjectNormalizer ()
         ],
         [
            new JsonEncoder (),
            new XmlEncoder (),
            new YamlEncoder (),
            new CsvEncoder ()
         ]
      );
   }
}
