<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Validator;
use App\Exceptions\ValidationException;
use Tests\Support\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredFieldsMustBePresent(): void
    {
        $validator = Validator::make(['name' => ''], ['name' => 'required|string']);

        $this->assertTrue($validator->fails());
        $this->assertTrue(isset($validator->errors()['name']));
    }

    public function testEmailRuleAcceptsAndRejects(): void
    {
        $this->assertTrue(Validator::make(['e' => 'a@b.co'], ['e' => 'required|email'])->passes());
        $this->assertTrue(Validator::make(['e' => 'not-an-email'], ['e' => 'required|email'])->fails());
        $this->assertTrue(Validator::make(['e' => 'a@b'], ['e' => 'required|email'])->fails());
    }

    public function testNullableSkipsRulesForEmptyValues(): void
    {
        $validator = Validator::make(['phone' => ''], ['phone' => 'nullable|phone']);

        $this->assertTrue($validator->passes());
        $this->assertNull($validator->validated()['phone']);
    }

    public function testLengthRulesMeasureCharactersForStrings(): void
    {
        $this->assertTrue(Validator::make(['t' => 'abc'], ['t' => 'required|min:3|max:5'])->passes());
        $this->assertTrue(Validator::make(['t' => 'ab'], ['t' => 'required|min:3'])->fails());
        $this->assertTrue(Validator::make(['t' => 'abcdef'], ['t' => 'required|max:5'])->fails());
    }

    public function testNumericFieldsCompareByValueNotLength(): void
    {
        // "5" is one character but the value is 5: a numeric min must compare
        // the number, or every small price would be rejected.
        $this->assertTrue(Validator::make(['p' => '5'], ['p' => 'required|numeric|min:1'])->passes());
        $this->assertTrue(Validator::make(['p' => '0'], ['p' => 'required|numeric|min:1'])->fails());
        $this->assertTrue(Validator::make(['p' => '1200'], ['p' => 'required|numeric|max:2000'])->passes());
    }

    public function testInRuleConstrainsToAList(): void
    {
        $this->assertTrue(Validator::make(['s' => 'active'], ['s' => 'required|in:draft,active'])->passes());
        $this->assertTrue(Validator::make(['s' => 'deleted'], ['s' => 'required|in:draft,active'])->fails());
    }

    public function testConfirmedRuleComparesTheConfirmationField(): void
    {
        $ok = Validator::make(
            ['password' => 'secret1234', 'password_confirmation' => 'secret1234'],
            ['password' => 'required|confirmed']
        );
        $mismatch = Validator::make(
            ['password' => 'secret1234', 'password_confirmation' => 'other'],
            ['password' => 'required|confirmed']
        );

        $this->assertTrue($ok->passes());
        $this->assertTrue($mismatch->fails());
    }

    public function testHexRuleValidatesColours(): void
    {
        $this->assertTrue(Validator::make(['c' => '#b08d57'], ['c' => 'required|hex'])->passes());
        $this->assertTrue(Validator::make(['c' => 'b08d57'], ['c' => 'required|hex'])->passes());
        $this->assertTrue(Validator::make(['c' => 'not-a-colour'], ['c' => 'required|hex'])->fails());
    }

    public function testValidateThrowsWithTheErrors(): void
    {
        $this->assertThrows(ValidationException::class, static function (): void {
            Validator::make([], ['name' => 'required'])->validate();
        });
    }

    public function testManuallyAddedErrorsSurvive(): void
    {
        // A controller adds uniqueness errors after the rules have run; a
        // second call to fails() must not wipe them.
        $validator = Validator::make(['email' => 'a@b.co'], ['email' => 'required|email']);

        $this->assertTrue($validator->passes());

        $validator->addError('email', 'Adresse déjà utilisée.');

        $this->assertTrue($validator->fails());
        $this->assertSame('Adresse déjà utilisée.', $validator->errors()['email']);
    }

    public function testOnlyValidatedKeysAreReturned(): void
    {
        $validator = Validator::make(
            ['name' => 'Jean', 'role' => 'SUPER_ADMIN'],
            ['name' => 'required|string']
        );

        $validator->passes();

        // Mass assignment defence: a field nobody declared never reaches the
        // repository, so an extra form input cannot set a column.
        $this->assertSame(['name' => 'Jean'], $validator->validated());
    }
}
