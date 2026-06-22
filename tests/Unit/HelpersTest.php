<?php

declare(strict_types=1);

it('converts money amount to uppercase chinese RMB text', function (): void {
    expect(money_to_zh(0))->toEqual('零元整')
        ->and(money_to_zh('1'))->toEqual('壹元整')
        ->and(money_to_zh('10.01'))->toEqual('壹拾元零壹分')
        ->and(money_to_zh('1001.10'))->toEqual('壹仟零壹元壹角')
        ->and(money_to_zh('100200300.45'))->toEqual('壹亿零贰拾万零叁佰元肆角伍分')
        ->and(money_to_zh('-12.30'))->toEqual('负壹拾贰元叁角')
        ->and(money_to_zh('12.345'))->toEqual('壹拾贰元叁角伍分');
});

it('keeps invalid money amount unchanged', function (): void {
    expect(money_to_zh('abc'))->toEqual('abc')
        ->and(money_to_zh(''))->toEqual('')
        ->and(money_to_zh(null))->toBeNull();
});
