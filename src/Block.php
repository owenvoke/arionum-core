<?php

namespace Arionum\Arionum;

/**
 * Class Block
 */
class Block
{
    /**
     * Add a new block to the blockchain.
     * @param int    $height
     * @param string $publicKey
     * @param string $nonce
     * @param array  $data
     * @param int    $date
     * @param string $signature
     * @param int    $difficulty
     * @param string $rewardSignature
     * @param string $argon
     * @return bool
     */
    public function add(
        int $height,
        string $publicKey,
        string $nonce,
        array $data,
        int $date,
        string $signature,
        int $difficulty,
        string $rewardSignature,
        string $argon
    ): bool {
        global $db;
        $acc = new Account();
        $trx = new Transaction();

        $generator = $acc->getAddress($publicKey);

        // The transactions are always sorted in the same way, on all nodes, as they are hashed as json
        ksort($data);

        // Create the hash / block id
        $hash = $this->hash($generator, $height, $date, $nonce, $data, $signature, $difficulty, $argon);

        // Fix for the broken base58 library used until block 16900, trimming the first 0 bytes.
        if ($height < 16900) {
            $hash = ltrim($hash, '1');
        }

        $json = json_encode($data);

        // Create the block data and check it against the signature
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";
        if (!$acc->checkSignature($info, $signature, $publicKey)) {
            _log('Block signature check failed');
            return false;
        }

        if (!$this->parseBlock($hash, $height, $data, true)) {
            _log('Parse block failed');
            return false;
        }

        // Lock table to avoid race conditions on blocks
        $db->exec('LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE');

        $reward = $this->reward($height, $data);

        $msg = '';

        // The reward transaction
        $transaction = [
            'src'        => $generator,
            'dst'        => $generator,
            'val'        => $reward,
            'version'    => 0,
            'date'       => $date,
            'message'    => $msg,
            'fee'        => '0.00000000',
            'public_key' => $publicKey,
        ];
        $transaction['signature'] = $rewardSignature;

        // Hash the transaction
        $transaction['id'] = $trx->hash($transaction);

        // Check the signature
        $info = $transaction['val'].'-'.$transaction['fee'].'-'.$transaction['dst'].'-'.$transaction['message'].'-'
            .$transaction['version'].'-'.$transaction['public_key'].'-'.$transaction['date'];

        if (!$acc->checkSignature($info, $rewardSignature, $publicKey)) {
            _log('Reward signature failed');
            return false;
        }

        // Insert the block into the database
        $db->beginTransaction();
        $total = count($data);
        $bind = [
            ':id'           => $hash,
            ':generator'    => $generator,
            ':signature'    => $signature,
            ':height'       => $height,
            ':date'         => $date,
            ':nonce'        => $nonce,
            ':difficulty'   => $difficulty,
            ':argon'        => $argon,
            ':transactions' => $total,
        ];

        $res = $db->run(
            'INSERT into blocks
             SET id = :id, generator = :generator, height = :height,`date` = :date, nonce = :nonce,
             signature = :signature, difficulty = :difficulty, argon = :argon, transactions = :transactions',
            $bind
        );

        if ($res !== 1) {
            // Rollback and exit if it fails
            _log('Block DB insert failed');
            $db->rollback();
            $db->exec('UNLOCK TABLES');

            return false;
        }

        // Insert the reward transaction in the db
        $trx->add($hash, $height, $transaction);

        // Parse the block's transactions and insert them to database
        $res = $this->parseBlock($hash, $height, $data, false);

        // If any fails, rollback
        if ($res == false) {
            $db->rollback();
        } else {
            $db->commit();
        }

        // Release the locking as everything is finished
        $db->exec('UNLOCK TABLES');

        return true;
    }

    /**
     * Get the current block, without the transactions.
     * @return array
     */
    public function current()
    {
        global $db;
        $current = $db->row('SELECT * FROM blocks ORDER by height DESC LIMIT 1');
        if (!$current) {
            $this->genesis();
            return $this->current(true);
        }
        return $current;
    }

    /**
     * Get the previous block.
     * @return array
     */
    public function prev()
    {
        global $db;
        $current = $db->row('SELECT * FROM blocks ORDER by height DESC LIMIT 1,1');

        return $current;
    }

    /**
     * Get the difficulty (or base target) for a specific block.
     * The higher the difficulty number, the easier it is to win a block.
     * @param int $height
     * @return bool|int|mixed|string
     */
    public function difficulty(int $height = 0)
    {
        global $db;

        // If no block height is specified, use the current block.
        if ($height == 0) {
            $current = $this->current();
        } else {
            $current = $this->get($height);
        }

        $height = $current['height'];

        // Hard fork 10900 resistance, force new difficulty
        if ($height === 10801) {
            return 5555555555;
        }

        // Last 20 blocks used to check the block times
        $limit = 20;
        if ($height < 20) {
            $limit = $height - 1;
        }

        // For the first 10 blocks, use the genesis difficulty
        if ($height < 10) {
            return $current['difficulty'];
        }

        // Elapsed time between the last 20 blocks
        $first = $db->row('SELECT `date` FROM blocks ORDER by height DESC LIMIT $limit,1');
        $time = $current['date'] - $first['date'];

        // Average block time
        $result = ceil($time / $limit);

        // Keep the current difficulty
        $difficulty = $current['difficulty'];

        // If larger than 200 sec, increase by 5%
        if ($result > 220) {
            $difficulty = bcmul($current['difficulty'], 1.05);
        }

        // If lower, decrease by 5%
        if ($result < 260) {
            $difficulty = bcmul($current['difficulty'], 0.95);
        }

        if (strpos($difficulty, '.') !== false) {
            $difficulty = substr($difficulty, 0, strpos($difficulty, '.'));
        }

        // Minimum and maximum difficulty
        if ($difficulty < 1000) {
            $difficulty = 1000;
        }

        // Maximum 'long double' difficulty
        if ($difficulty > 9223372036854775800) {
            $difficulty = 9223372036854775800;
        }

        return $difficulty;
    }

    /**
     * Calculate the maximum block size.
     * Increase by 10% the number of transactions if >100 on the last 100 blocks.
     * @return float|int
     */
    public function maxTransactions()
    {
        global $db;
        $current = $this->current();
        $limit = $current['height'] - 100;

        $avg = $db->single('SELECT AVG(transactions) FROM blocks WHERE height > :limit', [':limit' => $limit]);

        if ($avg < 100) {
            return 100;
        }

        return ceil($avg * 1.1);
    }

    /**
     * Calculate the reward for each block.
     * @param int   $id
     * @param array $data
     * @return string
     */
    public function reward(int $id, array $data = []): string
    {
        // Starting reward
        $reward = 1000;

        // Decrease by 1% each 10800 blocks (approx 1 month)
        $factor = floor($id / 10800) / 100;
        $reward -= $reward * $factor;

        if ($reward < 0) {
            $reward = 0;
        }

        // Calculate the transaction fees
        $fees = 0;
        if (count($data) > 0) {
            foreach ($data as $x) {
                $fees += $x['fee'];
            }
        }

        return number_format($reward + $fees, 8, '.', '');
    }

    /**
     * Check the validity of a block.
     * @param array $data
     * @return bool
     */
    public function check(array $data): bool
    {
        // Argon must have at least 20 chars
        if (strlen($data['argon']) < 20) {
            _log("Invalid block argon - $data[argon]");
            return false;
        }
        $acc = new Account();

        // Generators public key must be valid
        if (!$acc->validKey($data['public_key'])) {
            _log("Invalid public key - $data[public_key]");
            return false;
        }

        // Difficulty should be the same as our calculation
        if ($data['difficulty'] != $this->difficulty()) {
            _log("Invalid difficulty - $data[difficulty] - ".$this->difficulty());
            return false;
        }

        // Check the argon hash and the nonce to produce a valid block
        if (!$this->mine($data['public_key'], $data['nonce'], $data['argon'])) {
            _log('Mine check failed');
            return false;
        }

        return true;
    }

    /**
     * Create a new block on this node.
     * @param string $nonce
     * @param string $argon
     * @param string $publicKey
     * @param string $privateKey
     * @return bool
     */
    public function forge(string $nonce, string $argon, string $publicKey, string $privateKey): bool
    {
        // Check the argon hash and the nonce to produce a valid block
        if (!$this->mine($publicKey, $nonce, $argon)) {
            _log('Forge failed - Invalid argon');
            return false;
        }

        // The blocks date timestamp must be bigger than the last block
        $current = $this->current();
        $height = $current['height'] += 1;
        $date = time();
        if ($date <= $current['date']) {
            _log('Forge failed - Date older than last block');
            return false;
        }

        // Get the Mempool transactions
        $txn = new Transaction();
        $data = $txn->mempool($this->maxTransactions());

        $difficulty = $this->difficulty();
        $acc = new Account();
        $generator = $acc->getAddress($publicKey);

        // Always sort the transactions in the same way
        ksort($data);

        // Sign the block
        $signature = $this->sign($generator, $height, $date, $nonce, $data, $privateKey, $difficulty, $argon);

        // Reward transaction and signature
        $reward = $this->reward($height, $data);
        $msg = '';
        $transaction = [
            'src'        => $generator,
            'dst'        => $generator,
            'val'        => $reward,
            'version'    => 0,
            'date'       => $date,
            'message'    => $msg,
            'fee'        => '0.00000000',
            'public_key' => $publicKey,
        ];

        ksort($transaction);
        $rewardSignature = $txn->sign($transaction, $privateKey);

        // Add the block to the blockchain
        $res = $this->add(
            $height,
            $publicKey,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $rewardSignature,
            $argon
        );

        if (!$res) {
            _log("Forge failed - Block->Add() failed");
            return false;
        }

        return true;
    }

    /**
     * Check if the arguments are good for mining a specific block.
     * @param string $publicKey
     * @param string $nonce
     * @param string $argon
     * @param int    $difficulty
     * @param int    $currentId
     * @param int    $currentHeight
     * @return bool
     */
    public function mine(
        string $publicKey,
        string $nonce,
        string $argon,
        int $difficulty = 0,
        int $currentId = 0,
        int $currentHeight = 0
    ): bool {
        global $_config;

        // If no id is specified, we use the current
        if ($currentId === 0) {
            $current = $this->current();
            $currentId = $current['id'];
            $currentHeight = $current['height'];
        }

        // Get the current difficulty if empty
        if ($difficulty === 0) {
            $difficulty = $this->difficulty();
        }

        // The argon parameters are hardcoded to avoid any exploits
        $argon = '$argon2i$v=19$m=16384,t=4,p=4'.$argon;
        if ($currentHeight > 10800) {
            $argon = '$argon2i$v=19$m=524288,t=1,p=1'.$argon; //10800 block hard fork - resistance against gpu
        }

        // The hash base for argon
        $base = "$publicKey-$nonce-".$currentId."-$difficulty";

        // Check argon's hash validity
        if (!password_verify($base, $argon)) {
            return false;
        }

        // All nonces are valid in testnet
        if ($_config['testnet'] == true) {
            return true;
        }

        // Prepare the base for the hashing
        $hash = $base.$argon;

        // Hash the base 6 times
        for ($i = 0; $i < 5;
             $i++) {
            $hash = hash('sha512', $hash, true);
        }

        $hash = hash('sha512', $hash);

        // Split it in 2 char substrings, to be used as hex
        $m = str_split($hash, 2);

        // Calculate a number based on 8 hex numbers
        // No specific reason, we just needed an algoritm to generate the number from the hash
        $duration = hexdec($m[10]).hexdec($m[15]).hexdec($m[20]).hexdec($m[23])
            .hexdec($m[31]).hexdec($m[40]).hexdec($m[45]).hexdec($m[55]);

        // The number must not start with 0
        $duration = ltrim($duration, '0');

        // Divide the number by the difficulty and create the deadline
        $result = gmp_div($duration, $difficulty);

        // If the deadline >0 and <=240, the arguments are valid for a block win
        if ($result > 0 && $result <= 240) {
            return true;
        }

        return false;
    }

    /**
     * Parse the block transactions.
     * @param string $block
     * @param int    $height
     * @param array  $data
     * @param bool   $test
     * @return bool
     */
    public function parseBlock(string $block, int $height, array $data, bool $test = true): bool
    {
        global $db;

        // Data must be an array
        if ($data === false) {
            return false;
        }

        $account = new Account();
        $transaction = new Transaction();

        // No transactions means all are valid
        if (count($data) == 0) {
            return true;
        }

        // Check if the number of transactions is not bigger than current block size
        $max = $this->maxTransactions();
        if (count($data) > $max) {
            return false;
        }

        $balance = [];
        foreach ($data as &$x) {
            // Get the sender's account if empty
            if (empty($x['src'])) {
                $x['src'] = $account->getAddress($x['public_key']);
            }

            // Validate the transaction
            if (!$transaction->check($x, $height)) {
                return false;
            }

            // Prepare total balance
            $balance[$x['src']] += $x['val'] + $x['fee'];

            // Check if the transaction is already on the blockchain
            if ($db->single('SELECT COUNT(1) FROM transactions WHERE id=:id', [':id' => $x['id']]) > 0) {
                return false;
            }
        }

        // Check if the account has enough balance to perform the transaction
        foreach ($balance as $id => $bal) {
            $res = $db->single(
                'SELECT COUNT(1) FROM accounts WHERE id=:id AND balance>=:balance',
                [':id' => $id, ':balance' => $bal]
            );

            if ($res == 0) {
                return false; // not enough balance for the transactions
            }
        }

        // If the test argument is false, add the transactions to the blockchain
        if (!$test) {
            foreach ($data as $d) {
                $res = $transaction->add($block, $height, $d);
                if ($res == false) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Initialise the blockchain and add the genesis block.
     * @return void
     */
    private function genesis(): void
    {
        global $db;
        // phpcs:disable Generic.Files.LineLength
        $signature = 'AN1rKvtLTWvZorbiiNk5TBYXLgxiLakra2byFef9qoz1bmRzhQheRtiWivfGSwP6r8qHJGrf8uBeKjNZP1GZvsdKUVVN2XQoL';
        $generator = '2P67zUANj7NRKTruQ8nJRHNdKMroY6gLw4NjptTVmYk6Hh1QPYzzfEa9z4gv8qJhuhCNM8p9GDAEDqGUU1awaLW6';
        $publicKey = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSCyjGMdVDanywM3CbqvswVqysqU8XS87FcjpqNijtpRSSQ36WexRDv3rJL5X8qpGvzvznuErSRMfb2G6aNoiaT3aEJ';
        $rewardSignature = '381yXZ3yq2AXHHdXfEm8TDHS4xJ6nkV4suXtUUvLjtvuyi17jCujtwcwXuYALM1F3Wiae2A4yJ6pXL1kTHJxZbrJNgtsKEsb';
        $argon = '$M1ZpVzYzSUxYVFp6cXEwWA$CA6p39MVX7bvdXdIIRMnJuelqequanFfvcxzQjlmiik';
        // phpcs:enable

        $difficulty = '5555555555';
        $height = 1;
        $data = [];
        $date = '1515324995';
        $nonce = '4QRKTSJ+i9Gf9ubPo487eSi+eWOnIBt9w4Y+5J+qbh8=';

        $res = $this->add(
            $height,
            $publicKey,
            $nonce,
            $data,
            $date,
            $signature,
            $difficulty,
            $rewardSignature,
            $argon
        );

        if (!$res) {
            apiErr('Could not add the genesis block.');
        }
    }

    /**
     * Remove the last 'x' number of blocks.
     * @param int $blocksToRemove
     * @return void
     */
    public function pop($blocksToRemove = 1): void
    {
        $current = $this->current();
        $this->delete($current['height'] - $blocksToRemove + 1);
    }

    /**
     * Delete all blocks greater than or equal to the specified height.
     * @param int $height
     * @return bool
     */
    public function delete(int $height): bool
    {
        if ($height < 2) {
            $height = 2;
        }

        global $db;
        $trx = new Transaction();

        $r = $db->run('SELECT * FROM blocks WHERE height>=:height ORDER by height DESC', [":height" => $height]);

        if (count($r) === 0) {
            return false;
        }

        $db->beginTransaction();
        $db->exec('LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE');
        foreach ($r as $x) {
            $res = $trx->reverse($x['id']);

            if ($res === false) {
                $db->rollback();
                $db->exec('UNLOCK TABLES');

                return false;
            }

            $res = $db->run('DELETE FROM blocks WHERE id=:id', [':id' => $x['id']]);

            if ($res !== 1) {
                $db->rollback();
                $db->exec('UNLOCK TABLES');

                return false;
            }
        }

        $db->commit();
        $db->exec('UNLOCK TABLES');

        return true;
    }

    /**
     * Delete a block by its id.
     * @param string $id
     * @return bool
     */
    public function deleteId(string $id): bool
    {
        global $db;
        $trx = new Transaction();

        $x = $db->row('SELECT * FROM blocks WHERE id = :id', [':id' => $id]);

        if ($x === false) {
            return false;
        }

        // Avoid race conditions on blockchain manipulations
        $db->beginTransaction();
        $db->exec('LOCK TABLES blocks WRITE, accounts WRITE, transactions WRITE, mempool WRITE');

        // Reverse all transactions of the block
        $res = $trx->reverse($x['id']);
        if ($res === false) {
            // Rollback if you can't reverse the transactions
            $db->rollback();
            $db->exec('UNLOCK TABLES');
            return false;
        }

        // Remove the actual block
        $res = $db->run('DELETE FROM blocks WHERE id = :id', [':id' => $x['id']]);
        if ($res !== 1) {
            // Rollback if you can't delete the block
            $db->rollback();
            $db->exec('UNLOCK TABLES');
            return false;
        }

        // Commit and release if all good
        $db->commit();
        $db->exec('UNLOCK TABLES');

        return true;
    }

    /**
     * Sign a new block.
     * This is mostly used when mining.
     * @param string $generator
     * @param int    $height
     * @param int    $date
     * @param string $nonce
     * @param array  $data
     * @param string $key
     * @param int    $difficulty
     * @param string $argon
     * @return string
     */
    public function sign(
        string $generator,
        int $height,
        int $date,
        string $nonce,
        array $data,
        string $key,
        int $difficulty,
        string $argon
    ): string {
        $json = json_encode($data);
        $info = "{$generator}-{$height}-{$date}-{$nonce}-{$json}-{$difficulty}-{$argon}";

        $signature = ecSign($info, $key);
        return $signature;
    }

    /**
     * Generate the SHA512 hash of the block data and converts it to Base58.
     * @param string $publicKey
     * @param int    $height
     * @param int    $date
     * @param string $nonce
     * @param array  $data
     * @param string $signature
     * @param int    $difficulty
     * @param string $argon
     * @return string
     */
    public function hash(
        string $publicKey,
        int $height,
        int $date,
        string $nonce,
        array $data,
        string $signature,
        int $difficulty,
        string $argon
    ): string {
        $json = json_encode($data);
        $hash = hash('sha512', "{$publicKey}-{$height}-{$date}-{$nonce}-{$json}-{$signature}-{$difficulty}-{$argon}");
        return hexToCoin($hash);
    }

    /**
     * Export the block data, to be used when submitting to other peers.
     * @param string $id
     * @param string $height
     * @return bool
     */
    public function export(string $id = '', string $height = ''): bool
    {
        if (empty($id) && empty($height)) {
            return false;
        }

        global $db;

        if (!empty($height)) {
            $block = $db->row('SELECT * FROM blocks WHERE height = :height', [':height' => $height]);
        } else {
            $block = $db->row('SELECT * FROM blocks WHERE id = :id', [':id' => $id]);
        }

        if (!$block) {
            return false;
        }

        $r = $db->run('SELECT * FROM transactions WHERE version > 0 AND block = :block', [":block" => $block['id']]);
        $transactions = [];

        foreach ($r as $x) {
            $trans = [
                'id'         => $x['id'],
                'dst'        => $x['dst'],
                'val'        => $x['val'],
                'fee'        => $x['fee'],
                'signature'  => $x['signature'],
                'message'    => $x['message'],
                'version'    => $x['version'],
                'date'       => $x['date'],
                'public_key' => $x['public_key'],
            ];

            ksort($trans);
            $transactions[$x['id']] = $trans;
        }

        ksort($transactions);
        $block['data'] = $transactions;

        // The reward transaction always has version 0
        $gen = $db->row(
            'SELECT public_key, signature FROM transactions WHERE  version=0 AND block=:block',
            [':block' => $block['id']]
        );

        $block['public_key'] = $gen['public_key'];
        $block['reward_signature'] = $gen['signature'];

        return $block;
    }

    /**
     * Return a specific block as an array.
     * @param int $height
     * @return array|bool
     */
    public function get(int $height)
    {
        global $db;
        if (empty($height)) {
            return false;
        }
        $block = $db->row('SELECT * FROM blocks WHERE height = :height', [':height' => $height]);
        return $block;
    }
}
