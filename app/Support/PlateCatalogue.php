<?php

namespace App\Support;

/**
 * Every department code that can appear on the right-hand side of a French plate.
 *
 * Metropolitan France runs 01-95, except that 20 was split into Corsica's 2A and
 * 2B, which is why the metropolitan set lands on exactly 96 codes. The overseas
 * set covers the rare codes a car in France almost never shows.
 */
final class PlateCatalogue
{
    public const GROUP_METROPOLITAN = 'metropolitan';

    public const GROUP_OVERSEAS = 'overseas';

    // PHP casts numeric-looking array keys to integers, so codes are normalised
    // back to strings on the way out — see toPlates() and codes().
    private const METROPOLITAN = [
        '01' => 'Ain',
        '02' => 'Aisne',
        '03' => 'Allier',
        '04' => 'Alpes-de-Haute-Provence',
        '05' => 'Hautes-Alpes',
        '06' => 'Alpes-Maritimes',
        '07' => 'Ardèche',
        '08' => 'Ardennes',
        '09' => 'Ariège',
        '10' => 'Aube',
        '11' => 'Aude',
        '12' => 'Aveyron',
        '13' => 'Bouches-du-Rhône',
        '14' => 'Calvados',
        '15' => 'Cantal',
        '16' => 'Charente',
        '17' => 'Charente-Maritime',
        '18' => 'Cher',
        '19' => 'Corrèze',
        '2A' => 'Corse-du-Sud',
        '2B' => 'Haute-Corse',
        '21' => "Côte-d'Or",
        '22' => "Côtes-d'Armor",
        '23' => 'Creuse',
        '24' => 'Dordogne',
        '25' => 'Doubs',
        '26' => 'Drôme',
        '27' => 'Eure',
        '28' => 'Eure-et-Loir',
        '29' => 'Finistère',
        '30' => 'Gard',
        '31' => 'Haute-Garonne',
        '32' => 'Gers',
        '33' => 'Gironde',
        '34' => 'Hérault',
        '35' => 'Ille-et-Vilaine',
        '36' => 'Indre',
        '37' => 'Indre-et-Loire',
        '38' => 'Isère',
        '39' => 'Jura',
        '40' => 'Landes',
        '41' => 'Loir-et-Cher',
        '42' => 'Loire',
        '43' => 'Haute-Loire',
        '44' => 'Loire-Atlantique',
        '45' => 'Loiret',
        '46' => 'Lot',
        '47' => 'Lot-et-Garonne',
        '48' => 'Lozère',
        '49' => 'Maine-et-Loire',
        '50' => 'Manche',
        '51' => 'Marne',
        '52' => 'Haute-Marne',
        '53' => 'Mayenne',
        '54' => 'Meurthe-et-Moselle',
        '55' => 'Meuse',
        '56' => 'Morbihan',
        '57' => 'Moselle',
        '58' => 'Nièvre',
        '59' => 'Nord',
        '60' => 'Oise',
        '61' => 'Orne',
        '62' => 'Pas-de-Calais',
        '63' => 'Puy-de-Dôme',
        '64' => 'Pyrénées-Atlantiques',
        '65' => 'Hautes-Pyrénées',
        '66' => 'Pyrénées-Orientales',
        '67' => 'Bas-Rhin',
        '68' => 'Haut-Rhin',
        '69' => 'Rhône',
        '70' => 'Haute-Saône',
        '71' => 'Saône-et-Loire',
        '72' => 'Sarthe',
        '73' => 'Savoie',
        '74' => 'Haute-Savoie',
        '75' => 'Paris',
        '76' => 'Seine-Maritime',
        '77' => 'Seine-et-Marne',
        '78' => 'Yvelines',
        '79' => 'Deux-Sèvres',
        '80' => 'Somme',
        '81' => 'Tarn',
        '82' => 'Tarn-et-Garonne',
        '83' => 'Var',
        '84' => 'Vaucluse',
        '85' => 'Vendée',
        '86' => 'Vienne',
        '87' => 'Haute-Vienne',
        '88' => 'Vosges',
        '89' => 'Yonne',
        '90' => 'Territoire de Belfort',
        '91' => 'Essonne',
        '92' => 'Hauts-de-Seine',
        '93' => 'Seine-Saint-Denis',
        '94' => 'Val-de-Marne',
        '95' => "Val-d'Oise",
    ];

    private const OVERSEAS = [
        '971' => 'Guadeloupe',
        '972' => 'Martinique',
        '973' => 'Guyane',
        '974' => 'La Réunion',
        '975' => 'Saint-Pierre-et-Miquelon',
        '976' => 'Mayotte',
        '977' => 'Saint-Barthélemy',
        '978' => 'Saint-Martin',
        '984' => 'Terres australes',
        '986' => 'Wallis-et-Futuna',
        '987' => 'Polynésie française',
        '988' => 'Nouvelle-Calédonie',
    ];

    /**
     * @return list<array{code: string, name: string, group: string}>
     */
    public static function metropolitan(): array
    {
        return self::toPlates(self::METROPOLITAN, self::GROUP_METROPOLITAN);
    }

    /**
     * @return list<array{code: string, name: string, group: string}>
     */
    public static function overseas(): array
    {
        return self::toPlates(self::OVERSEAS, self::GROUP_OVERSEAS);
    }

    /**
     * @return list<array{code: string, name: string, group: string}>
     */
    public static function all(): array
    {
        return array_merge(self::metropolitan(), self::overseas());
    }

    /**
     * Metropolitan plates split into decades so the board can be scanned.
     *
     * Corsica's 2A and 2B belong to the twenties: they replaced department 20.
     *
     * @return list<array{label: string, plates: list<array{code: string, name: string, group: string}>}>
     */
    public static function metropolitanDecades(): array
    {
        $buckets = [];

        foreach (self::metropolitan() as $plate) {
            $bucket = str_starts_with($plate['code'], '2') && ! ctype_digit($plate['code'])
                ? 20
                : intdiv((int) $plate['code'], 10) * 10;

            $buckets[$bucket][] = $plate;
        }

        ksort($buckets);

        $decades = [];

        foreach ($buckets as $bucket => $plates) {
            $first = $plates[0]['code'];
            $last = $plates[count($plates) - 1]['code'];
            $decades[] = ['label' => $first.'–'.$last, 'plates' => $plates];
        }

        return $decades;
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(
            static fn (int|string $code): string => (string) $code,
            array_merge(
                array_keys(self::METROPOLITAN),
                array_keys(self::OVERSEAS),
            ),
        );
    }

    public static function count(): int
    {
        return count(self::METROPOLITAN) + count(self::OVERSEAS);
    }

    /**
     * @param  array<int|string, string>  $departments
     * @return list<array{code: string, name: string, group: string}>
     */
    private static function toPlates(array $departments, string $group): array
    {
        $plates = [];

        foreach ($departments as $code => $name) {
            $plates[] = ['code' => (string) $code, 'name' => $name, 'group' => $group];
        }

        return $plates;
    }
}
