<?php

use App\Services\Ai\MatchEvent;

describe('MatchEvent', function () {

    it('creates from array with all fields', function () {
        $data = [
            'type'                        => 'animal_on_pitch',
            'description'                 => 'Bir martı 81. dakikada topa çarptı.',
            'minute'                       => 81,
            'severity'                    => 'minor',
            'match_suspended'             => false,
            'suspension_duration_minutes' => 0,
            'player_impacts'              => [
                ['player_role' => 'goalkeeper', 'morale_delta' => -5, 'stress_delta' => 10],
            ],
            'team_impacts'                => [
                ['team' => 'home', 'morale_delta' => 5, 'chemistry_delta' => 2],
            ],
            'permanent_consequences'      => [],
            'media_value'                 => 'medium',
            'historical_precedent'        => '2012 İskoçya Ligi — kartal sahaya indi',
        ];

        $event = MatchEvent::fromArray($data);

        expect($event->type)->toBe('animal_on_pitch')
            ->and($event->minute)->toBe(81)
            ->and($event->severity)->toBe('minor')
            ->and($event->matchSuspended)->toBeFalse()
            ->and($event->playerImpacts)->toHaveCount(1)
            ->and($event->mediaValue)->toBe('medium')
            ->and($event->historicalPrecedent)->toBe('2012 İskoçya Ligi — kartal sahaya indi');
    });

    it('handles missing optional fields gracefully', function () {
        $event = MatchEvent::fromArray(['type' => 'flare_incident']);

        expect($event->type)->toBe('flare_incident')
            ->and($event->description)->toBe('')
            ->and($event->minute)->toBe(0)
            ->and($event->severity)->toBe('minor')
            ->and($event->matchSuspended)->toBeFalse()
            ->and($event->playerImpacts)->toBeEmpty()
            ->and($event->teamImpacts)->toBeEmpty()
            ->and($event->permanentConsequences)->toBeEmpty()
            ->and($event->historicalPrecedent)->toBeNull();
    });

    it('requiresMatchAbandonment returns true for critical severity', function () {
        $event = MatchEvent::fromArray(['type' => 'stadium_incident', 'severity' => 'critical']);
        expect($event->requiresMatchAbandonment())->toBeTrue();
    });

    it('requiresMatchAbandonment returns true when match_suspended is true', function () {
        $event = MatchEvent::fromArray([
            'type'             => 'crowd_disturbance',
            'severity'         => 'major',
            'match_suspended'  => true,
        ]);
        expect($event->requiresMatchAbandonment())->toBeTrue();
    });

    it('requiresMatchAbandonment returns false for minor events', function () {
        $event = MatchEvent::fromArray(['type' => 'animal_on_pitch', 'severity' => 'minor']);
        expect($event->requiresMatchAbandonment())->toBeFalse();
    });

    it('isNewsworthy returns true for high and viral media value', function () {
        $high  = MatchEvent::fromArray(['type' => 'player_medical', 'media_value' => 'high']);
        $viral = MatchEvent::fromArray(['type' => 'pitch_invasion', 'media_value' => 'viral']);
        $low   = MatchEvent::fromArray(['type' => 'flare_incident', 'media_value' => 'low']);

        expect($high->isNewsworthy())->toBeTrue()
            ->and($viral->isNewsworthy())->toBeTrue()
            ->and($low->isNewsworthy())->toBeFalse();
    });

    it('converts back to array', function () {
        $data  = ['type' => 'referee_scandal', 'minute' => 45, 'severity' => 'major', 'media_value' => 'high'];
        $event = MatchEvent::fromArray($data);
        $arr   = $event->toArray();

        expect($arr['type'])->toBe('referee_scandal')
            ->and($arr['minute'])->toBe(45)
            ->and($arr['media_value'])->toBe('high');
    });

});
