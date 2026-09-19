<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\TestModel;

/**
 * Unit tests for Model.
 * No database connection required - pure validation and attribute logic testing.
 */
class ModelTest extends TestCase
{
    // ---------------------------------------------------------------
    //  Validation
    // ---------------------------------------------------------------

    public function testValidateFails(): void
    {
        $model = new TestModel();
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('attr1'));
    }

    public function testValidatePasses(): void
    {
        $model = new TestModel();
        $model->scenario = 'insert';
        $model->attr1 = 3;
        $model->attr2 = 3;

        $this->assertTrue($model->validate());
        $this->assertFalse($model->hasErrors());
    }

    public function testValidateWithScenario(): void
    {
        $model = new TestModel();
        $model->scenario = 'register';
        $model->attr1 = 3;
        $model->attr2 = 3;

        // Should fail because password is required in 'register' scenario
        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('password'));

        // Add password and should pass
        $model->password = 'secret';
        $model->password2 = 'secret';
        $this->assertTrue($model->validate());
    }

    public function testValidateCompareFails(): void
    {
        $model = new TestModel();
        $model->scenario = 'register';
        $model->attr1 = 3;
        $model->attr2 = 3;
        $model->password = 'secret';
        $model->password2 = 'different';

        $this->assertFalse($model->validate());
        $this->assertTrue($model->hasErrors('password2'));
    }

    public function testValidateSpecificAttributes(): void
    {
        $model = new TestModel();
        $model->attr1 = 10; // invalid (max 5)
        $model->attr2 = 10; // invalid (max 5)

        // Validate only attr1
        $this->assertFalse($model->validate(['attr1']));
        $this->assertTrue($model->hasErrors('attr1'));
        $this->assertFalse($model->hasErrors('attr2')); // not validated
    }

    // ---------------------------------------------------------------
    //  Errors
    // ---------------------------------------------------------------

    public function testGetErrors(): void
    {
        $model = new TestModel();
        $model->validate();

        $errors = $model->getErrors();
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('attr1', $errors);
    }

    public function testGetErrorsForAttribute(): void
    {
        $model = new TestModel();
        $model->validate();

        $errors = $model->getErrors('attr1');
        $this->assertIsArray($errors);
        $this->assertNotEmpty($errors);
    }

    public function testGetError(): void
    {
        $model = new TestModel();
        $model->validate();

        $error = $model->getError('attr1');
        $this->assertIsString($error);
        $this->assertNotEmpty($error);
    }

    public function testAddError(): void
    {
        $model = new TestModel();
        $model->validate();
        $model->clearErrors();

        $model->addError('attr1', 'Custom error message');
        $this->assertTrue($model->hasErrors('attr1'));
        $this->assertSame('Custom error message', $model->getError('attr1'));
    }

    public function testAddErrors(): void
    {
        $model = new TestModel();
        $model->validate();
        $model->clearErrors();

        $model->addErrors([
            'attr1' => 'Error 1',
            'attr2' => ['Error 2a', 'Error 2b'],
        ]);

        $this->assertTrue($model->hasErrors('attr1'));
        $this->assertTrue($model->hasErrors('attr2'));
        $this->assertCount(1, $model->getErrors('attr1'));
        $this->assertCount(2, $model->getErrors('attr2'));
    }

    public function testClearErrors(): void
    {
        $model = new TestModel();
        $model->validate();

        $this->assertTrue($model->hasErrors());

        $model->clearErrors();
        $this->assertFalse($model->hasErrors());
    }

    public function testClearErrorsForAttribute(): void
    {
        $model = new TestModel();
        $model->validate();

        $this->assertTrue($model->hasErrors('attr1'));

        $model->clearErrors('attr1');
        $this->assertFalse($model->hasErrors('attr1'));
    }

    // ---------------------------------------------------------------
    //  Attributes
    // ---------------------------------------------------------------

    public function testGetAttributes(): void
    {
        $model = new TestModel();
        $model->attr1 = 1;
        $model->attr2 = 2;

        $attributes = $model->getAttributes();
        $this->assertIsArray($attributes);
        $this->assertEquals(1, $attributes['attr1']);
        $this->assertEquals(2, $attributes['attr2']);
    }

    public function testGetAttributesSpecific(): void
    {
        $model = new TestModel();
        $model->attr1 = 1;
        $model->attr2 = 2;

        $attributes = $model->getAttributes(['attr1']);
        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayNotHasKey('attr2', $attributes);
    }

    public function testSetAttributes(): void
    {
        $model = new TestModel();
        $model->setAttributes([
            'attr1' => 10,
            'attr2' => 20,
        ], false);

        $this->assertEquals(10, $model->attr1);
        $this->assertEquals(20, $model->attr2);
    }

    public function testSetAttributesSafeOnly(): void
    {
        $model = new TestModel();
        $model->setAttributes([
            'attr1' => 10,
            'attr3' => 'unsafe value',
        ], true);

        $this->assertEquals(10, $model->attr1);
        $this->assertNull($model->attr3); // unsafe, not set
    }

    public function testUnsetAttributes(): void
    {
        $model = new TestModel();
        $model->attr1 = 1;
        $model->attr2 = 2;

        $model->unsetAttributes(['attr1']);
        $this->assertNull($model->attr1);
        $this->assertEquals(2, $model->attr2);
    }

    public function testUnsetAllAttributes(): void
    {
        $model = new TestModel();
        $model->attr1 = 1;
        $model->attr2 = 2;

        $model->unsetAttributes();
        $this->assertNull($model->attr1);
        $this->assertNull($model->attr2);
    }

    // ---------------------------------------------------------------
    //  Safe attributes
    // ---------------------------------------------------------------

    public function testGetSafeAttributeNames(): void
    {
        $model = new TestModel();
        $safeAttributes = $model->getSafeAttributeNames();

        $this->assertIsArray($safeAttributes);
        $this->assertContains('attr1', $safeAttributes);
        $this->assertContains('attr2', $safeAttributes);
        $this->assertNotContains('attr3', $safeAttributes); // unsafe
    }

    public function testIsAttributeSafe(): void
    {
        $model = new TestModel();

        $this->assertTrue($model->isAttributeSafe('attr1'));
        $this->assertTrue($model->isAttributeSafe('attr2'));
        $this->assertFalse($model->isAttributeSafe('attr3')); // unsafe
    }

    public function testIsAttributeRequired(): void
    {
        $model = new TestModel();

        $this->assertTrue($model->isAttributeRequired('attr1'));
        $this->assertFalse($model->isAttributeRequired('attr2')); // not required
    }

    // ---------------------------------------------------------------
    //  Labels
    // ---------------------------------------------------------------

    public function testGetAttributeLabel(): void
    {
        $model = new TestModel();

        $this->assertSame('First Attribute', $model->getAttributeLabel('attr1'));
        $this->assertSame('Second Attribute', $model->getAttributeLabel('attr2'));
    }

    public function testGenerateAttributeLabel(): void
    {
        $model = new TestModel();

        $this->assertSame('Department Name', $model->generateAttributeLabel('department_name'));
        $this->assertSame('First Name', $model->generateAttributeLabel('firstName'));
        $this->assertSame('Last Name', $model->generateAttributeLabel('LastName'));
    }

    // ---------------------------------------------------------------
    //  Scenario
    // ---------------------------------------------------------------

    public function testGetSetScenario(): void
    {
        $model = new TestModel();

        $this->assertSame('', $model->getScenario());

        $model->setScenario('register');
        $this->assertSame('register', $model->getScenario());
    }

    // ---------------------------------------------------------------
    //  ArrayAccess
    // ---------------------------------------------------------------

    public function testArrayAccess(): void
    {
        $model = new TestModel();

        $model['attr1'] = 5;
        $this->assertEquals(5, $model['attr1']);
        $this->assertTrue(isset($model['attr1']));

        $model['attr1'] = null;
        $this->assertNull($model['attr1']);
    }

    // ---------------------------------------------------------------
    //  Iterator
    // ---------------------------------------------------------------

    public function testIterator(): void
    {
        $model = new TestModel();
        $model->attr1 = 1;
        $model->attr2 = 2;

        $attributes = [];
        foreach ($model as $name => $value) {
            $attributes[$name] = $value;
        }

        $this->assertArrayHasKey('attr1', $attributes);
        $this->assertArrayHasKey('attr2', $attributes);
    }
}
