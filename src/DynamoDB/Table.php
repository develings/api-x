<?php

namespace ApiX\DynamoDB;

/**
 * Class Table
 * @package API\DynamoDB
 * @see https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/aws-resource-dynamodb-table.html#cfn-dynamodb-table-billingmode
 */
class Table
{
    private $tableName;
    private $attributeDefinitions = [];
    private $keySchema = [];
    private $readCapacityUnits = 1;
    private $writeCapacityUnits = 1;
    private $streamEnabled = true;
    /**
     * When an item in the table is modified, StreamViewType determines what information is written
     * to the stream for this table.
     * @var string
     */
    private $streamViewType = self::STREAM_TYPE_KEYS_ONLY;
    
    /**
     * Specify how you are charged for read and write throughput and how you manage capacity.
     * @var string
     */
    private $billingMode = self::BILLING_TYPE_PROVISIONED;
    private $tags = [];
    
    public const TYPE_STRING = 'S';
    public const TYPE_NUMBER = 'N';
    public const TYPE_BINARY = 'B';
    public const KEY_TYPE_HASH = 'HASH';
    public const KEY_TYPE_RANGE = 'RANGE';
    
    public const BILLING_TYPE_PROVISIONED = 'PROVISIONED';
    public const BILLING_TYPE_PAY_PER_REQUEST = 'PAY_PER_REQUEST';
    
    // Only the key attributes of the modified item are written to the stream.
    public const STREAM_TYPE_KEYS_ONLY = 'KEYS_ONLY';
    
    // The entire item, as it appears after it was modified, is written to the stream.
    public const STREAM_TYPE_NEW_IMAGE = 'NEW_IMAGE';
    
    // The entire item, as it appeared before it was modified, is written to the stream.
    public const STREAM_TYPE_OLD_IMAGE = 'OLD_IMAGE';
    
    // Both the new and the old item images of the item are written to the stream.
    public const STREAM_TYPE_NEW_AND_OLD_IMAGES = 'NEW_AND_OLD_IMAGES';
    
    /**
     * Table constructor.
     *
     * @param $tableName
     */
    public function __construct($tableName)
    {
        $this->tableName = $tableName;
    }
    
    public function addAttribute(string $name, string $type = self::TYPE_STRING)
    {
        $this->attributeDefinitions[] = [
            'AttributeName' => $name,
            'AttributeType' => $type,
        ];
    }
    
    public function addKey(string $name, string $type = self::KEY_TYPE_HASH)
    {
        $this->keySchema[] = [
            'AttributeName' => $name,
            'KeyType' => $type,
        ];
    }
    
    public function addTag(string $key, string $value)
    {
        $this->tags[] = [
            'Key' => $key,
            'Value' => $value,
        ];
    }
    
    /**
     * @return mixed
     */
    public function getTableName()
    {
        return $this->tableName;
    }
    
    /**
     * @param mixed $tableName
     */
    public function setTableName($tableName): void
    {
        $this->tableName = $tableName;
    }
    
    /**
     * @return array
     */
    public function getAttributeDefinitions(): array
    {
        return $this->attributeDefinitions;
    }
    
    /**
     * @param array $attributeDefinitions
     */
    public function setAttributeDefinitions(array $attributeDefinitions): void
    {
        $this->attributeDefinitions = $attributeDefinitions;
    }
    
    /**
     * @return array
     */
    public function getKeySchema(): array
    {
        return $this->keySchema;
    }
    
    /**
     * @param array $keySchema
     */
    public function setKeySchema(array $keySchema): void
    {
        $this->keySchema = $keySchema;
    }
    
    /**
     * @return int
     */
    public function getReadCapacityUnits(): int
    {
        return $this->readCapacityUnits;
    }
    
    /**
     * @param int $readCapacityUnits
     */
    public function setReadCapacityUnits(int $readCapacityUnits): void
    {
        $this->readCapacityUnits = $readCapacityUnits;
    }
    
    /**
     * @return int
     */
    public function getWriteCapacityUnits(): int
    {
        return $this->writeCapacityUnits;
    }
    
    /**
     * @param int $writeCapacityUnits
     */
    public function setWriteCapacityUnits(int $writeCapacityUnits): void
    {
        $this->writeCapacityUnits = $writeCapacityUnits;
    }
    
    /**
     * @return bool
     */
    public function isStreamEnabled(): bool
    {
        return $this->streamEnabled;
    }
    
    /**
     * @param bool $streamEnabled
     */
    public function setStreamEnabled(bool $streamEnabled): void
    {
        $this->streamEnabled = $streamEnabled;
    }
    
    /**
     * @return string
     */
    public function getStreamViewType(): string
    {
        return $this->streamViewType;
    }
    
    /**
     * @param string $streamViewType
     */
    public function setStreamViewType(string $streamViewType): void
    {
        $this->streamViewType = $streamViewType;
    }
    
    /**
     * @return string
     */
    public function getBillingMode(): string
    {
        return $this->billingMode;
    }
    
    /**
     * @param string $billingMode
     */
    public function setBillingMode(string $billingMode): void
    {
        $this->billingMode = $billingMode;
    }
    
    /**
     * @return mixed
     */
    public function getTags()
    {
        return $this->tags;
    }
    
    /**
     * @param mixed $tags
     */
    public function setTags($tags): void
    {
        $this->tags = $tags;
    }
    
    public function toArray()
    {
        return [
            'AttributeDefinitions' => $this->attributeDefinitions,
            'TableName' => $this->tableName,
            'KeySchema' => $this->keySchema,
            'BillingType' => $this->billingMode,
            'ProvisionedThroughput' => [
                'ReadCapacityUnits' => $this->readCapacityUnits,
                'WriteCapacityUnits' => $this->writeCapacityUnits,
            ],
            'StreamSpecification' => [
                'StreamEnabled' => $this->streamEnabled,
                'StreamViewType' => $this->streamViewType,
            ],
            'Tags' => $this->tags,
        ];
    }
}
