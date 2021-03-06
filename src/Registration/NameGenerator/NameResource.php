<?php

declare(strict_types=1);

namespace TelegramOSINT\Registration\NameGenerator;

class NameResource
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $lastName;
    /** @var array */
    private static $names = [];

    public function __construct()
    {
        if (!self::$names) {
            self::$names = json_decode(file_get_contents(__DIR__.'/names.json'), true);
        }

        $this->name = self::$names[array_rand(self::$names)];
        $this->lastName = self::$names[array_rand(self::$names)];
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getLastName()
    {
        return $this->lastName;
    }
}
