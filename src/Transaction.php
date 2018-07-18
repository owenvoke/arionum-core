<?php

namespace Arionum\Arionum;

/**
 * Class Transaction
 */
class Transaction extends Model
{
    /**
     * Reverse and remove all transactions from a block.
     * @param string $block
     * @return bool
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function reverse(string $block): bool
    {
        $account = new Account($this->config, $this->database);
        $transactions = $this->database->run('SELECT * FROM transactions WHERE block = :block', [':block' => $block]);

        foreach ($transactions as $transaction) {
            if (empty($transaction['src'])) {
                $transaction['src'] = $account->getAddress($transaction['public_key']);
            }

            $this->database->run(
                'UPDATE accounts SET balance = balance - :val WHERE id = :id',
                [':id' => $transaction['dst'], ':val' => $transaction['val']]
            );

            // On version 0 / reward transaction, don't credit anyone
            if ($transaction['version'] > 0) {
                $this->database->run(
                    'UPDATE accounts SET balance = balance + :val WHERE id = :id',
                    [':id' => $transaction['src'], ':val' => $transaction['val'] + $transaction['fee']]
                );
            }

            // Add the transactions to mempool
            if ($transaction['version'] > 0) {
                $this->addMempool($transaction);
            }

            $result = $this->database->run('DELETE FROM transactions WHERE id = :id', [':id' => $transaction['id']]);
            if ($result != 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear the Mempool.
     * @return void
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function cleanMempool(): void
    {
        $block = new Block($this->config, $this->database);
        $current = $block->current();

        $height = $current['height'];
        $limit = $height - 1000;

        $this->database->run('DELETE FROM mempool WHERE height < :limit', [':limit' => $limit]);
    }

    /**
     * Get 'x' transactions from Mempool.
     * @param int $max
     * @return array
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function mempool(int $max): array
    {
        $block = new Block($this->config, $this->database);

        $current = $block->current();
        $height = $current['height'] + 1;

        // Only get the transactions that are not locked with a future height
        $transactions = $this->database->run(
            'SELECT * FROM mempool WHERE height <= :height ORDER by val/fee DESC LIMIT :max',
            [':height' => $height, ':max' => $max + 50]
        );
        $results = [];
        if (count($transactions) > 0) {
            $i = 0;
            $balance = [];
            foreach ($transactions as $transaction) {
                $trans = [
                    'id'         => $transaction['id'],
                    'dst'        => $transaction['dst'],
                    'val'        => $transaction['val'],
                    'fee'        => $transaction['fee'],
                    'signature'  => $transaction['signature'],
                    'message'    => $transaction['message'],
                    'version'    => $transaction['version'],
                    'date'       => $transaction['date'],
                    'public_key' => $transaction['public_key'],
                ];

                if ($i >= $max) {
                    break;
                }

                if (empty($transaction['public_key'])) {
                    _log($transaction['id'].' - Transaction has empty public_key');
                    continue;
                }

                if (empty($transaction['src'])) {
                    _log($transaction['id'].' - Transaction has empty src');
                    continue;
                }

                if (!$this->check($trans, $current['height'])) {
                    _log($transaction['id'].' - Transaction Check Failed');
                    continue;
                }

                $balance[$transaction['src']] += $transaction['val'] + $transaction['fee'];
                if ($this->database->single(
                    'SELECT COUNT(1) FROM transactions WHERE id=:id',
                    [':id' => $transaction['id']]
                ) > 0
                ) {
                    // Duplicate transaction
                    _log($transaction['id'].' - Duplicate transaction');
                    continue;
                }

                $result = $this->database->single(
                    'SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance',
                    [':id' => $transaction['src'], ':balance' => $balance[$transaction['src']]]
                );

                if ($result == 0) {
                    // Not enough balance for the transactions
                    _log($transaction['id'].' - Not enough funds in balance');
                    continue;
                }

                $i++;
                ksort($trans);
                $results[$transaction['id']] = $trans;
            }
        }

        // Always sort the array
        ksort($results);

        return $results;
    }

    /**
     * Add a new transaction to Mempool and lock it with the current height.
     * @param array  $transactionData
     * @param string $peer
     * @return bool
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function addMempool(array $transactionData, string $peer = ''): bool
    {
        $block = new Block($this->config, $this->database);
        $current = $block->current();

        $height = $current['height'];
        $transactionData['id'] = san($transactionData['id']);

        $bind = [
            ':peer'       => $peer,
            ':id'         => $transactionData['id'],
            ':public_key' => $transactionData['public_key'],
            ':height'     => $height,
            ':src'        => $transactionData['src'],
            ':dst'        => $transactionData['dst'],
            ':val'        => $transactionData['val'],
            ':fee'        => $transactionData['fee'],
            ':signature'  => $transactionData['signature'],
            ':version'    => $transactionData['version'],
            ':date'       => $transactionData['date'],
            ':message'    => $transactionData['message'],
        ];

        $this->database->run(
            'INSERT into mempool
             SET peer = :peer, id = :id, public_key = :public_key, height = :height, src = :src, dst = :dst,
             val = :val, fee = :fee, signature = :signature, version = :version, message = :message, `date` = :date',
            $bind
        );

        return true;
    }

    /**
     * Add a new transaction to the blockchain.
     * @param string $block
     * @param int    $height
     * @param array  $transactionData
     * @return bool
     */
    public function add(string $block, int $height, array $transactionData): bool
    {
        $acc = new Account($this->config, $this->database);

        $acc->add($transactionData['public_key'], $block);
        $acc->addId($transactionData['dst'], $block);
        $transactionData['id'] = san($transactionData['id']);

        $bind = [
            ':id'         => $transactionData['id'],
            ':public_key' => $transactionData['public_key'],
            ':height'     => $height,
            ':block'      => $block,
            ':dst'        => $transactionData['dst'],
            ':val'        => $transactionData['val'],
            ':fee'        => $transactionData['fee'],
            ':signature'  => $transactionData['signature'],
            ':version'    => $transactionData['version'],
            ':date'       => $transactionData['date'],
            ':message'    => $transactionData['message'],
        ];

        $result = $this->database->run(
            'INSERT into transactions
             SET id = :id, public_key = :public_key, block = :block,  height = :height, dst = :dst, val = :val,
            fee = :fee, signature = :signature, version = :version, message = :message, `date` = :date',
            $bind
        );

        if ($result != 1) {
            return false;
        }

        $this->database->run(
            'UPDATE accounts SET balance = balance+:val WHERE id = :id',
            [":id" => $transactionData['dst'], ":val" => $transactionData['val']]
        );

        // No debit when the transaction is reward
        if ($transactionData['version'] > 0) {
            $this->database->run(
                'UPDATE accounts SET balance = (balance - :val) - :fee WHERE id = :id',
                [":id" => $transactionData['src'], ":val" => $transactionData['val'], ":fee" => $transactionData['fee']]
            );
        }

        $this->database->run('DELETE FROM mempool WHERE id = :id', [':id' => $transactionData['id']]);

        return true;
    }

    /**
     * Hash the transaction's most important fields and create the transaction ID.
     * @param array $transactionData
     * @return string
     */
    public function hash(array $transactionData): string
    {
        $transactionInfo = $transactionData['val'].'-'.$transactionData['fee'].'-'.$transactionData['dst'].'-'
            .$transactionData['message'].'-'.$transactionData['version'].'-'.$transactionData['public_key'].'-'
            .$transactionData['date'].'-'.$transactionData['signature'];
        $hash = hash('sha512', $transactionInfo);
        return hexToCoin($hash);
    }

    /**
     * Check the validity of a transaction.
     * @param array $transactionData
     * @param int   $height
     * @return bool
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function check(array $transactionData, int $height = 0): bool
    {
        // If no block is specified, use the current block
        if ($height === 0) {
            $block = new Block($this->config, $this->database);
            $current = $block->current();
            $height = $current['height'];
        }

        $acc = new Account($this->config, $this->database);
        $transactionInfo = $transactionData['val'].'-'.$transactionData['fee'].'-'.$transactionData['dst'].'-'
            .$transactionData['message'].'-'.$transactionData['version'].'-'.$transactionData['public_key'].'-'
            .$transactionData['date'];

        // The value must be >=0
        if ($transactionData['val'] < 0) {
            _log($transactionData['id'].' - Value below 0');
            return false;
        }

        // The fee must be >=0
        if ($transactionData['fee'] < 0) {
            _log($transactionData['id'].' - Fee below 0');
            return false;
        }

        // The fee is 0.25%, hardcoded
        $fee = $transactionData['val'] * 0.0025;
        $fee = number_format($fee, 8, ".", "");
        if ($fee < 0.00000001) {
            $fee = 0.00000001;
        }
        // Maximum fee after block 10800 is 10
        if ($height > 10800 && $fee > 10) {
            $fee = 10; //10800
        }
        // Added fee does not match
        if ($fee != $transactionData['fee']) {
            _log($transactionData['id'].' - Fee not 0.25%');
            return false;
        }

        // Invalid destination address
        if (!$acc->valid($transactionData['dst'])) {
            _log($transactionData['id'].' - Invalid destination address');
            return false;
        }

        // Reward transactions are not added via this function
        if ($transactionData['version'] < 1) {
            _log($transactionData['id'].' - Invalid version <1');
            return false;
        }

        // Public key must be at least 15 chars / probably should be replaced with the validator function
        if (strlen($transactionData['public_key']) < 15) {
            _log($transactionData['id'].' - Invalid public key size');
            return false;
        }

        // No transactions before the genesis
        if ($transactionData['date'] < 1511725068) {
            _log($transactionData['id'].' - Date before genesis');
            return false;
        }

        // No future transactions
        if ($transactionData['date'] > time() + 86400) {
            _log($transactionData['id'].' - Date in the future');
            return false;
        }

        // Prevent the resending of broken base58 transactions
        if ($height > 16900 && $transactionData['date'] < 1519327780) {
            return false;
        }

        $transactionId = $this->hash($transactionData);

        // The hash does not match our regenerated hash
        if ($transactionData['id'] !== $transactionId) {
            // Fix for broken Base58 library which was used until block 16900.
            // Accept hashes without the first 1 or 2 bytes.
            $xs = base58Decode($transactionData['id']);
            if (((strlen($xs) !== 63 || substr($transactionId, 1) !== $transactionData['id'])
                    && (strlen($xs) !== 62 || substr($transactionId, 2) !== $transactionData['id']))
                || $height > 16900
            ) {
                _log($transactionData['id'].' - '.$transactionId.' - Invalid hash');
                return false;
            }
        }

        // Verify the ECDSA signature
        if (!$acc->checkSignature($transactionInfo, $transactionData['signature'], $transactionData['public_key'])) {
            _log($transactionData['id'].' - Invalid signature');
            return false;
        }

        return true;
    }

    /**
     * Sign a transaction.
     * @param array  $transactionData
     * @param string $privateKey
     * @return string
     */
    public function sign(array $transactionData, string $privateKey): string
    {
        $transactionInfo = $transactionData['val'].'-'.$transactionData['fee'].'-'.$transactionData['dst'].'-'
            .$transactionData['message'].'-'.$transactionData['version'].'-'.$transactionData['public_key'].'-'
            .$transactionData['date'];
        $signature = ecSign($transactionInfo, $privateKey);

        return $signature;
    }

    /**
     * Export a Mempool transaction.
     * @param string $id
     * @return array
     */
    public function export(string $id): array
    {
        return $this->database->row('SELECT * FROM mempool WHERE id = :id', [':id' => $id]);
    }

    /**
     * Get the transaction data as array.
     * @param string $transactionId
     * @return array|bool
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function getTransaction(string $transactionId)
    {
        $acc = new Account($this->config, $this->database);
        $block = new Block($this->config, $this->database);
        $current = $block->current();

        $transactionData = $this->database->row('SELECT * FROM transactions WHERE id = :id', [':id' => $transactionId]);

        if (!$transactionData) {
            return false;
        }

        $transaction = [
            'block'      => $transactionData['block'],
            'height'     => $transactionData['height'],
            'id'         => $transactionData['id'],
            'dst'        => $transactionData['dst'],
            'val'        => $transactionData['val'],
            'fee'        => $transactionData['fee'],
            'signature'  => $transactionData['signature'],
            'message'    => $transactionData['message'],
            'version'    => $transactionData['version'],
            'date'       => $transactionData['date'],
            'public_key' => $transactionData['public_key'],
        ];

        $transaction['src'] = $acc->getAddress($transactionData['public_key']);
        $transaction['confirmations'] = $current['height'] - $transactionData['height'];

        $transaction['type'] = 'other';

        if ($transactionData['version'] == 0) {
            $transaction['type'] = 'mining';
        } elseif ($transactionData['version'] == 1) {
            $transaction['type'] = 'debit';

            if ($transactionData['dst'] == $transactionId) {
                $transaction['type'] = 'credit';
            }
        }

        ksort($transaction);
        return $transaction;
    }

    /**
     * Get the transactions for a specific block id or height.
     * @param string $height
     * @param string $transactionId
     * @return array|bool
     * @throws Exceptions\ConfigPropertyNotFoundException
     */
    public function getTransactions($height = '', $transactionId = '')
    {
        $block = new Block($this->config, $this->database);
        $current = $block->current();
        $acc = new Account($this->config, $this->database);

        $height = san($height);
        $transactionId = san($transactionId);

        if (empty($transactionId) && empty($height)) {
            return false;
        }

        if (!empty($transactionId)) {
            $transactions = $this->database->run(
                'SELECT * FROM transactions WHERE block = :id AND version > 0',
                [':id' => $transactionId]
            );
        } else {
            $transactions = $this->database->run(
                'SELECT * FROM transactions WHERE height = :height AND version > 0',
                [':height' => $height]
            );
        }

        $results = [];
        foreach ($transactions as $transaction) {
            $transactionResult = [
                'block'      => $transaction['block'],
                'height'     => $transaction['height'],
                'id'         => $transaction['id'],
                'dst'        => $transaction['dst'],
                'val'        => $transaction['val'],
                'fee'        => $transaction['fee'],
                'signature'  => $transaction['signature'],
                'message'    => $transaction['message'],
                'version'    => $transaction['version'],
                'date'       => $transaction['date'],
                'public_key' => $transaction['public_key'],
            ];

            $transactionResult['src'] = $acc->getAddress($transaction['public_key']);
            $transactionResult['confirmations'] = $current['height'] - $transaction['height'];

            if ($transaction['version'] == 0) {
                $transactionResult['type'] = 'mining';
            } elseif ($transaction['version'] == 1) {
                if ($transaction['dst'] == $transactionId) {
                    $transactionResult['type'] = 'credit';
                } else {
                    $transactionResult['type'] = 'debit';
                }
            } else {
                $transactionResult['type'] = 'other';
            }

            ksort($transactionResult);
            $results[] = $transactionResult;
        }

        return $results;
    }

    /**
     * Get a specific Mempool transaction as an array.
     * @param string $id
     * @return array|bool
     */
    public function getMempoolTransaction(string $id)
    {
        $transactionData = $this->database->row('SELECT * FROM mempool WHERE id=:id', [':id' => $id]);

        if (!$transactionData) {
            return false;
        }

        $transaction = [
            'block'      => $transactionData['block'],
            'height'     => $transactionData['height'],
            'id'         => $transactionData['id'],
            'dst'        => $transactionData['dst'],
            'val'        => $transactionData['val'],
            'fee'        => $transactionData['fee'],
            'signature'  => $transactionData['signature'],
            'message'    => $transactionData['message'],
            'version'    => $transactionData['version'],
            'date'       => $transactionData['date'],
            'public_key' => $transactionData['public_key'],
        ];

        $transaction['src'] = $transactionData['src'];

        $transaction['type'] = 'mempool';
        $transaction['confirmations'] = -1;

        ksort($transaction);

        return $transaction;
    }
}
