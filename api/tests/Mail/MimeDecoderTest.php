<?php

namespace App\Tests\Mail;

use App\Mail\MimeDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MimeDecoderTest extends TestCase
{
    #[DataProvider('cases')]
    public function testDecodesEncodedWords(string $in, string $out): void
    {
        self::assertSame($out, MimeDecoder::decode($in));
        self::assertSame($out, MimeDecoder::decode(MimeDecoder::decode($in)), 'idempotent');
    }

    public static function cases(): iterable
    {
        yield 'Q accentué + suite' => ['=?utf-8?Q?R=C3=A9cap?= du matin', 'Récap du matin'];
        yield 'point médian' => ['Bilan du soir =?utf-8?Q?=C2=B7?= 2026-11-20', 'Bilan du soir · 2026-11-20'];
        yield 'base64' => ['=?UTF-8?B?UsOpY2Fw?= du soir', 'Récap du soir'];
        yield 'plusieurs mots adjacents' => ['=?utf-8?Q?R=C3=A9?= =?utf-8?Q?cap?=', 'Récap'];
        yield 'latin-1' => ['=?ISO-8859-1?Q?Caf=E9?=', 'Café'];
        yield 'texte simple' => ['Devis des affiches', 'Devis des affiches'];
        yield 'vide' => ['', ''];
        yield 'signe égal sans mot encodé' => ['a =? b', 'a =? b'];
    }

    public function testUnknownCharsetNeverLosesTheSubject(): void
    {
        self::assertNotSame('', MimeDecoder::decode('=?x-inconnu?Q?abc?= suite'));
    }
}
