<?php

namespace App\Enum;

enum Language: string
{
    case English = 'en';
    case Bulgarian = 'bl';
    case Russian = 'ru';
    case Arabic = 'ar';
    case BrazilianPortuguese = 'br';
    case Japanese = 'jp';
    case Korean = 'ko';
    case French = 'fr';
    case Chinese = 'cn';
    case Czech = 'cz';
    case Hungarian = 'hu';
    case Portuguese = 'pt';
    case Turkish = 'tr';
    case Dutch = 'nl';
    case Romanian = 'ro';
    case Spanish = 'es';
    case Polish = 'pl';
    case Italian = 'it';
    case German = 'de';
    case Danish = 'da';
    case Swedish = 'sv';
    case Finnish = 'fi';
    case Norwegian = 'no';
    case MexicanSpanish = 'es_mx';
    case Icelandic = 'is';
    case Ukrainian = 'uk';
    case Thai = 'th';
    case SimplifiedChinese = 'zh';
    public function getLocalName(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Bulgarian => 'български',
            self::Russian => 'русский',
            self::Arabic => 'العربية',
            self::BrazilianPortuguese => 'Português do Brasil',
            self::Japanese => '日本語',
            self::Korean => '한국어',
            self::French => 'français',
            self::Chinese => '中文(简体)',
            self::Czech => 'český',
            self::Hungarian => 'magyar',
            self::Portuguese => 'português',
            self::Turkish => 'Türkçe',
            self::Dutch => 'nederlands',
            self::Romanian => 'română',
            self::Spanish => 'español',
            self::Polish => 'polski',
            self::Italian => 'italiano',
            self::German => 'Deutsch',
            self::Danish => 'Dansk',
            self::Swedish => 'svenska',
            self::Finnish => 'suomi',
            self::Norwegian => 'norsk',
            self::MexicanSpanish => 'Español (AL)',
            self::Icelandic => 'Íslenska',
            self::Ukrainian => 'yкраїнська',
            self::Thai => 'ไทย',
            self::SimplifiedChinese => '中文(繁體)',
        };
    }
}
