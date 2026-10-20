<?php

namespace AviSaaS\Connector\Classes;

final class ExtensionList
{
    private const POS_KEY = 'pos';

    private const POS_SERVICES = ['delivery', 'takeaway', 'waiter', 'dinein'];

    private const POS_GROUP_LABEL = 'Point of Sale services';

    private const TABS = [
        'all' => ['All', 'No extensions are listed yet.'],
        'installed' => ['Installed', 'Nothing installed yet.'],
        'updates' => ['Updates', 'Everything is up to date.'],
        'notowned' => ['Not owned', 'You own every extension.'],
    ];

    public function __construct(private readonly RowStatus $rowStatus) {}

    public function build(array $rows): array
    {
        $entries = [];
        $counts = array_fill_keys(array_keys(self::TABS), 0);

        foreach ($this->arrange($rows) as $item) {
            if (isset($item['group'])) {
                $entries[] = ['kind' => 'group', 'label' => $item['group']];

                continue;
            }

            $status = $this->rowStatus->describe($item['row']);

            foreach ($status['tabs'] as $tab) {
                $counts[$tab]++;
            }

            $entries[] = ['kind' => 'row', 'row' => $item['row'], 'status' => $status, 'sub' => $item['sub']];
        }

        $tabs = [];

        foreach (self::TABS as $id => [$label, $empty]) {
            $tabs[] = ['id' => $id, 'label' => $label, 'empty' => $empty, 'count' => $counts[$id], 'hot' => $id === 'updates' && $counts[$id] > 0];
        }

        return ['entries' => $entries, 'tabs' => $tabs];
    }

    private function arrange(array $rows): array
    {
        $rows = array_values($rows);
        $posAt = array_search(self::POS_KEY, array_column($rows, 'key'), true);

        if ($posAt === false) {
            return array_map(fn (array $row): array => ['row' => $row, 'sub' => false], $rows);
        }

        $services = array_filter($rows, fn (array $row): bool => in_array($row['key'], self::POS_SERVICES, true));
        usort($services, fn (array $a, array $b): int => array_search($a['key'], self::POS_SERVICES, true) <=> array_search($b['key'], self::POS_SERVICES, true));

        $arranged = [];

        foreach ($rows as $index => $row) {
            if (in_array($row['key'], self::POS_SERVICES, true)) {
                continue;
            }

            $arranged[] = ['row' => $row, 'sub' => false];

            if ($index === $posAt && $services !== []) {
                $arranged[] = ['group' => self::POS_GROUP_LABEL];

                foreach ($services as $service) {
                    $arranged[] = ['row' => $service, 'sub' => true];
                }
            }
        }

        return $arranged;
    }
}
