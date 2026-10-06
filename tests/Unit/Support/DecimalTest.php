<?php

use App\Support\Decimal;

it('normalizes valid decimal strings and blanks invalid input to zero', function () {
    expect(Decimal::normalize(' 12.50 '))->toBe('12.50')
        ->and(Decimal::normalize('0'))->toBe('0')
        ->and(Decimal::normalize(''))->toBe('0')
        ->and(Decimal::normalize('-1'))->toBe('0')
        ->and(Decimal::normalize('abc'))->toBe('0');
});

it('trims trailing zeros and a dangling decimal point', function () {
    expect(Decimal::trim('12.5000'))->toBe('12.5')
        ->and(Decimal::trim('12.0000'))->toBe('12')
        ->and(Decimal::trim('0.0000'))->toBe('0')
        ->and(Decimal::trim('42'))->toBe('42');
});

it('adds subtracts multiplies and divides decimal strings', function () {
    expect(Decimal::add('1.5', '2.25'))->toBe('3.75')
        ->and(Decimal::sub('10', '2.5'))->toBe('7.5')
        ->and(Decimal::mul('2', '3.5'))->toBe('7')
        ->and(Decimal::div('10', '4'))->toBe('2.5');
});

it('returns zero when dividing by zero', function () {
    expect(Decimal::div('10', '0'))->toBe('0')
        ->and(Decimal::div('10', ''))->toBe('0');
});

it('compares and picks min and max', function () {
    expect(Decimal::compare('1', '2'))->toBe(-1)
        ->and(Decimal::compare('2', '2'))->toBe(0)
        ->and(Decimal::compare('3', '2'))->toBe(1)
        ->and(Decimal::min('5', '3'))->toBe('3')
        ->and(Decimal::max('5', '3'))->toBe('5');
});
