<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\ExistValidationModel;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\UniqueValidationModel;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\User;

abstract class AbstractValidationRulesTest extends AbstractDatabaseTest
{
    private const string EXISTING_USERNAME = 'user1';
    private const string EXISTING_EMAIL = 'user1@example.com';

    public static function setUpBeforeClass(): void
    {
        User::model()->refreshMetaData();
        UniqueValidationModel::model()->refreshMetaData();
        ExistValidationModel::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;
    }

    protected function tearDown(): void
    {
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    private function uniqueModel(string $scenario): UniqueValidationModel
    {
        $model = new UniqueValidationModel();
        $model->scenario = $scenario;

        return $model;
    }

    private function existModel(string $scenario): ExistValidationModel
    {
        $model = new ExistValidationModel();
        $model->scenario = $scenario;

        return $model;
    }

    /**
     * Whether the test database compares strings case-insensitively (e.g. MySQL default collation).
     */
    private function isCaseInsensitiveCollation(): bool
    {
        $count = (int)$this->connection
            ->createCommand('SELECT COUNT(*) FROM users WHERE username = :username')
            ->queryScalar([':username' => strtoupper(self::EXISTING_USERNAME)]);

        return $count > 0;
    }

    // ===== UniqueRule =====

    public function testUniqueValueIsValid(): void
    {
        $model = $this->uniqueModel('simple');
        $model->username = 'brand_new_user';

        $this->assertTrue($model->validate(['username']));
    }

    public function testExistingRecordKeepingOwnValueIsValid(): void
    {
        $model = UniqueValidationModel::model()->findByPk(1);
        $this->assertNotNull($model);
        $model->scenario = 'simple';

        $this->assertTrue($model->validate(['username']));
    }

    public function testDuplicateValueIsInvalid(): void
    {
        $model = $this->uniqueModel('simple');
        $model->username = self::EXISTING_USERNAME;

        $this->assertFalse($model->validate(['username']));
        $this->assertTrue($model->hasErrors('username'));
    }

    public function testExistingRecordChangingToDuplicateIsInvalid(): void
    {
        $model = UniqueValidationModel::model()->findByPk(1);
        $this->assertNotNull($model);
        $model->scenario = 'simple';
        $model->username = 'user2';

        $this->assertFalse($model->validate(['username']));
        $this->assertTrue($model->hasErrors('username'));
    }

    public function testDuplicateValueDetectedAfterInsert(): void
    {
        $first = $this->uniqueModel('simple');
        $first->username = 'inserted_user';
        $first->password = 'secret';
        $first->email = 'inserted@example.com';
        $this->assertTrue($first->save(false));

        $this->assertTrue($first->validate(['username']));

        $second = $this->uniqueModel('simple');
        $second->username = 'inserted_user';

        $this->assertFalse($second->validate(['username']));
    }

    public function testCaseInsensitiveDetectsDuplicate(): void
    {
        $model = $this->uniqueModel('case_insensitive');
        $model->username = strtoupper(self::EXISTING_USERNAME);

        $this->assertFalse($model->validate(['username']));
    }

    public function testCaseSensitiveRespectsCollation(): void
    {
        $model = $this->uniqueModel('case_sensitive');
        $model->username = strtoupper(self::EXISTING_USERNAME);

        $this->assertSame(!$this->isCaseInsensitiveCollation(), $model->validate(['username']));
    }

    public function testCustomMessage(): void
    {
        $model = $this->uniqueModel('custom_message');
        $model->username = self::EXISTING_USERNAME;

        $this->assertFalse($model->validate(['username']));
        $this->assertSame('username "user1" already used.', $model->getError('username'));
    }

    public function testAttributeName(): void
    {
        $model = $this->uniqueModel('attribute_name');
        $model->username = self::EXISTING_EMAIL;

        $this->assertFalse($model->validate(['username']));

        $model->username = 'nobody@example.com';
        $this->assertTrue($model->validate(['username']));
    }

    public function testClassNameOption(): void
    {
        $model = $this->uniqueModel('class_name');
        $model->username = self::EXISTING_USERNAME;

        $this->assertFalse($model->validate(['username']));
    }

    public function testCriteriaNarrowsCheck(): void
    {
        $model = $this->uniqueModel('criteria');
        $model->username = self::EXISTING_USERNAME;

        // The existing row has id=1, so "id > 100" makes the value look unique
        $this->assertTrue($model->validate(['username']));
    }

    public function testAllowEmpty(): void
    {
        $model = $this->uniqueModel('allow_empty');
        $model->username = '';

        $this->assertTrue($model->validate(['username']));
    }

    public function testArrayValueIsInvalid(): void
    {
        $model = $this->uniqueModel('simple');
        $model->username = ['user1'];

        $this->assertFalse($model->validate(['username']));
        $this->assertTrue($model->hasErrors('username'));
    }

    public function testMissingColumnThrows(): void
    {
        $model = $this->uniqueModel('missing_column');
        $model->username = 'anything';

        $this->expectException(DbException::class);
        $this->expectExceptionMessage('does not have a column named "does_not_exist"');

        $model->validate(['username']);
    }

    // ===== ExistRule =====

    public function testExistingValueIsValid(): void
    {
        $model = $this->existModel('simple');
        $model->username = self::EXISTING_USERNAME;

        $this->assertTrue($model->validate(['username']));
    }

    public function testNonExistingValueIsInvalid(): void
    {
        $model = $this->existModel('simple');
        $model->username = 'does_not_exist';

        $this->assertFalse($model->validate(['username']));
        $this->assertTrue($model->hasErrors('username'));
    }

    public function testExistingValueWithDefaultMessage(): void
    {
        $model = $this->existModel('simple');
        $model->username = 'does_not_exist';

        $this->assertFalse($model->validate(['username']));
        $this->assertSame('username "does_not_exist" is invalid.', $model->getError('username'));
    }

    public function testCaseInsensitiveFindsValue(): void
    {
        $model = $this->existModel('case_insensitive');
        $model->username = strtoupper(self::EXISTING_USERNAME);

        $this->assertTrue($model->validate(['username']));
    }

    public function testExistCaseSensitiveRespectsCollation(): void
    {
        $model = $this->existModel('case_sensitive');
        $model->username = strtoupper(self::EXISTING_USERNAME);

        $this->assertSame($this->isCaseInsensitiveCollation(), $model->validate(['username']));
    }

    public function testExistCustomMessage(): void
    {
        $model = $this->existModel('custom_message');
        $model->username = 'does_not_exist';

        $this->assertFalse($model->validate(['username']));
        $this->assertSame('username "does_not_exist" must exist.', $model->getError('username'));
    }

    public function testExistAttributeName(): void
    {
        $model = $this->existModel('attribute_name');
        $model->username = self::EXISTING_EMAIL;

        $this->assertTrue($model->validate(['username']));

        $model->username = 'nobody@example.com';
        $this->assertFalse($model->validate(['username']));
    }

    public function testExistClassNameOption(): void
    {
        $model = $this->existModel('class_name');
        $model->username = self::EXISTING_USERNAME;

        $this->assertTrue($model->validate(['username']));
    }

    public function testExistCriteriaNarrowsCheck(): void
    {
        $model = $this->existModel('criteria');
        $model->username = self::EXISTING_USERNAME;

        // The existing row has id=1, so "id > 100" makes it look non-existing
        $this->assertFalse($model->validate(['username']));
    }

    public function testExistAllowEmpty(): void
    {
        $model = $this->existModel('allow_empty');
        $model->username = '';

        $this->assertTrue($model->validate(['username']));
    }

    public function testExistArrayValueIsInvalid(): void
    {
        $model = $this->existModel('simple');
        $model->username = ['user1'];

        $this->assertFalse($model->validate(['username']));
        $this->assertTrue($model->hasErrors('username'));
    }

    public function testExistMissingColumnThrows(): void
    {
        $model = $this->existModel('missing_column');
        $model->username = 'anything';

        $this->expectException(DbException::class);
        $this->expectExceptionMessage('does not have a column named "does_not_exist"');

        $model->validate(['username']);
    }
}
