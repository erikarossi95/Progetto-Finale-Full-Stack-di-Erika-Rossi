<?php

declare(strict_types=1);

namespace Snaply\Tests\Core;

use PHPUnit\Framework\TestCase;
use Snaply\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testValidPayloadHasNoErrors(): void
    {
        $v = new Validator();
        $v->required('name', 'Erika', 'Il nome')->length('name', 'Erika', 2, 100, 'Il nome');
        $v->required('email', 'erika@example.com', "L'email")->email('email', 'erika@example.com');
        $v->minPassword('password', 'supersegreta');

        $this->assertFalse($v->fails());
        $this->assertSame([], $v->errors());
    }

    public function testCollectsFieldErrors(): void
    {
        $v = new Validator();
        $v->required('name', '', 'Il nome');
        $v->email('email', 'non-valida');
        $v->minPassword('password', '123');

        $this->assertTrue($v->fails());
        $errors = $v->errors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testFirstErrorWinsPerField(): void
    {
        $v = new Validator();
        $v->required('name', '', 'Il nome')->length('name', '', 2, 100, 'Il nome');
        // Deve restare il primo messaggio (obbligatorio), non sovrascritto.
        $this->assertStringContainsString('obbligatorio', $v->errors()['name']);
    }

    public function testDateAndHexColor(): void
    {
        $v = new Validator();
        $v->date('d1', '2026-07-12')->date('d2', '12-07-2026');
        $v->hexColor('c1', '#6C5CE7')->hexColor('c2', 'viola');

        $errors = $v->errors();
        $this->assertArrayNotHasKey('d1', $errors);
        $this->assertArrayHasKey('d2', $errors);
        $this->assertArrayNotHasKey('c1', $errors);
        $this->assertArrayHasKey('c2', $errors);
    }

    public function testOptionalEmptyValuesAreSkipped(): void
    {
        $v = new Validator();
        $v->date('d', null)->hexColor('c', '')->email('e', '');
        $this->assertFalse($v->fails());
    }
}
