<?php

namespace Arionum\Arionum\Helpers;

use PHPUnit\Framework\TestCase;

/**
 * Class KeysTest
 */
class KeysTest extends TestCase
{
    // phpcs:disable Generic.Files.LineLength
    private const BLOCK_ID = '3mH5xjQVyXKkynsUS3WaLLgu1124WdMiykgeB81o2QstRPhxkCya6y8P6NnDoFFjbDQDpixqbPKxR44e2QedMmZx';
    private const COIN_AS_HEX = '8a37934b3854939e43125c50072c77fee6815b85e8a67549482a6824275a15d5c2749b147f374f5285376071e6a43ea61e3a3762bc68298b4e651b0e96a69467';
    private const PUBLIC_KEY = 'PZ8Tyr4Nx8MHsRAGMpZmZ6TWY63dXWSD1Hm7fGpQAgh1goGj8G47RmU68i3mP4erGGrJ1LNBzEy4di4jZKA2Z6ee96VxaDMUnzSthyzMSyhqF1DbLwNKPim2';
    private const PRIVATE_KEY = 'Lzhp9LopCDbzk3eSdzuL5f9cR9ng12s6gNonQET3kSLtZU4MbQVreDRFjoWcEdUyeUN3tKwpR4AuakWfT6LeCg4trqQ2YSy2q1pUCJppyPBFW89m3xZKhFgMhJgApkevYxYyn1GPDEpmuSUkYhDfEf68xrGNYAhEc';
    private const PUBLIC_HEX_KEY = '3056301006072a8648ce3d020106052b8104000a03420004f6aa5db1e18d992ef264edd1f3970129bdd015956424c2ca6f8752aac7ef2d9b6107967bf930519a68b8575c556a32f07ec51c1142797fdc8a35511c4e85b86d';
    private const PRIVATE_HEX_KEY = '307402010104202da74832e5ffaf23133e1a88e47dd937b85476d1a20cbe0036bfff3ea18f3ec2a00706052b8104000aa14403420004f6aa5db1e18d992ef264edd1f3970129bdd015956424c2ca6f8752aac7ef2d9b6107967bf930519a68b8575c556a32f07ec51c1142797fdc8a35511c4e85b86d';
    private const PUBLIC_PEM_KEY = '-----BEGIN PUBLIC KEY-----
MFYwEAYHKoZIzj0CAQYFK4EEAAoDQgAE9qpdseGNmS7yZO3R85cBKb3QFZVkJMLK
b4dSqsfvLZthB5Z7+TBRmmi4V1xVajLwfsUcEUJ5f9yKNVEcToW4bQ==
-----END PUBLIC KEY-----
';
    private const PRIVATE_PEM_KEY = '-----BEGIN EC PRIVATE KEY-----
MHQCAQEEIC2nSDLl/68jEz4aiOR92Te4VHbRogy+ADa//z6hjz7CoAcGBSuBBAAK
oUQDQgAE9qpdseGNmS7yZO3R85cBKb3QFZVkJMLKb4dSqsfvLZthB5Z7+TBRmmi4
V1xVajLwfsUcEUJ5f9yKNVEcToW4bQ==
-----END EC PRIVATE KEY-----
';
    // phpcs:enable

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsABase58ValueToHexadecimal()
    {
        $this->assertEquals(self::COIN_AS_HEX, Keys::coinToHex(self::BLOCK_ID));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAHexadecimalValueToBase58()
    {
        $this->assertEquals(self::BLOCK_ID, Keys::hexToCoin(self::COIN_AS_HEX));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPublicBase58KeyToPemFormat()
    {
        $this->assertEquals(self::PUBLIC_PEM_KEY, Keys::coinToPem(self::PUBLIC_KEY));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPrivateBase58KeyToPemFormat()
    {
        $this->assertEquals(self::PRIVATE_PEM_KEY, Keys::coinToPem(self::PRIVATE_KEY, true));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPublicPemKeyToBase58Format()
    {
        $this->assertEquals(self::PUBLIC_KEY, Keys::pemToCoin(self::PUBLIC_PEM_KEY));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPublicPemKeyToHexadecimal()
    {
        $this->assertEquals(self::PUBLIC_HEX_KEY, Keys::pemToHex(self::PUBLIC_PEM_KEY));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPrivatePemKeyToHexadecimal()
    {
        $this->assertEquals(self::PRIVATE_HEX_KEY, Keys::pemToHex(self::PRIVATE_PEM_KEY));
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPublicHexadecimalKeyToPem()
    {
        $this->assertEquals(
            $this->toSingleLine(self::PUBLIC_PEM_KEY),
            $this->toSingleLine(Keys::hexToPem(self::PUBLIC_HEX_KEY))
        );
    }

    /**
     * @test
     * @throws \Exception
     */
    public function itConvertsAPrivateHexadecimalKeyToPem()
    {
        $this->assertEquals(
            $this->toSingleLine(self::PRIVATE_PEM_KEY),
            $this->toSingleLine(Keys::hexToPem(self::PRIVATE_HEX_KEY, true))
        );
    }

    /**
     * @param string $data
     * @return string
     */
    private function toSingleLine(string $data): string
    {
        return str_replace(["\r", "\n"], '', $data);
    }
}
