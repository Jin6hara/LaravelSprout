<?php
// 元Enum 3/3
namespace App\Services\Calendar;

class CandidateEvent
{
    public string $title;
    public string $start;
    public ?string $end = null;
    public bool $allDay = true;
    public array $classNames = [];
    public array $extendedProps = [];

    public int $level = 9;
    public string $type = EventType::BACKGROUND;
    public string $planGroup = PlanGroup::REGULAR_PLAN;
    public ?string $dateKey = null;

    public function __construct(array $a)
    {
        foreach ($a as $k => $v) {
            $this->$k = $v;
        }
        if (!$this->dateKey) {
            $this->dateKey = substr($this->start, 0, 10);
        }
        $this->extendedProps['level'] = $this->level;
    }

    public function toArray(): array
    {
        $extendedProps = $this->extendedProps;
        $extendedProps['level'] = $extendedProps['level'] ?? $this->level;
        $extendedProps['type'] = $extendedProps['type'] ?? $this->type;
        $extendedProps['plan_group'] = $extendedProps['plan_group'] ?? $this->planGroup;

        return [
            'title' => $this->title,
            'start' => $this->start,
            'end'   => $this->end,
            'allDay' => $this->allDay,
            'classNames' => $this->classNames,
            'display' => $this->type, // ←追加（背景塗りつぶしスタイル）provoder側での指定は無効（なぜか不明）。
            'extendedProps' => $extendedProps,
        ];
    }
}
