<?php

namespace Arionum\Core\Traits;

use Arionum\Core\Helpers\Sanitise;

/**
 * Trait HasAlias
 */
trait HasAlias
{
    /**
     * Check if an alias is not in use.
     * @param string $alias
     * @return bool
     */
    public function freeAlias(string $alias): bool
    {
        $originalAlias = $alias;
        $alias = strtoupper($alias);
        $alias = Sanitise::alphanumeric($alias);

        if (strlen($alias) < 4 || strlen($alias) > 25) {
            return false;
        }

        if ($originalAlias !== $alias) {
            return false;
        }

        $result = $this->database->single('SELECT COUNT(1) FROM accounts WHERE alias = :alias', [':alias' => $alias]);

        return $result === 0;
    }

    /**
     * Check if an account has an address.
     * @param string $publicKey
     * @return bool
     */
    public function hasAlias(string $publicKey): bool
    {
        $publicKey = Sanitise::alphanumeric($publicKey);
        $res = $this->database->single(
            'SELECT COUNT(1) FROM accounts WHERE public_key = :public_key AND alias IS NOT NULL',
            [':public_key' => $publicKey]
        );

        return $res !== 0;
    }

    /**
     * Check that an alias is valid.
     * @param $alias
     * @return bool
     */
    public function validateAlias(string $alias): bool
    {
        $orig = $alias;
        $banned = [
            "MERCURY",
            "DEVS",
            "DEVELOPMENT",
            "MARKETING",
            "MERCURY80",
            "DEVARO",
            "DEVELOPER",
            "DEVELOPERS",
            "ARODEV",
            "DONATION",
            "MERCATOX",
            "OCTAEX",
            "MERCURY",
            "ARIONUM",
            "ESCROW",
            "OKEX",
            "BINANCE",
            "CRYPTOPIA",
            "HUOBI",
            "ITFINEX",
            "HITBTC",
            "UPBIT",
            "COINBASE",
            "KRAKEN",
            "BITSTAMP",
            "BITTREX",
            "POLONIEX",
        ];

        $alias = Sanitise::alphanumeric(strtoupper($alias));

        if (in_array($alias, $banned)) {
            return false;
        }

        if (strlen($alias) < 4 || strlen($alias) > 25) {
            return false;
        }

        if ($orig !== $alias) {
            return false;
        }

        return $this->database->single('SELECT COUNT(1) FROM accounts WHERE alias = :alias', [':alias' => $alias]);
    }

    /**
     * Get an account from a specific alias.
     * @param string $alias
     * @return string
     */
    public function aliasToAccount(string $alias): string
    {
        $alias = strtoupper($alias);

        return $this->database->single('SELECT id FROM accounts WHERE alias = :alias LIMIT 1', [':alias' => $alias]);
    }

    /**
     * Get an alias for a specific address.
     * @param string $address
     * @return string
     */
    public function accountToAlias(string $address): string
    {
        $address = Sanitise::alphanumeric($address);

        return $this->database->single('SELECT alias FROM accounts WHERE id = :id LIMIT 1', [':id' => $address]);
    }
}
