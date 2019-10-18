<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Write\Traits;

use Improved as i;
use Jasny\DB\Exception\BuildQueryException;
use Jasny\DB\Mongo\Query\WriteQuery;
use Jasny\DB\Mongo\QueryBuilder\SaveQueryBuilder;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\QueryBuilder\QueryBuilderInterface;
use Jasny\DB\QueryBuilder\StagedQueryBuilder;
use Jasny\DB\Result\Result;
use Jasny\DB\Result\ResultBuilder;
use MongoDB\BulkWriteResult;
use MongoDB\Collection;

/**
 * Save data to a MongoDB collection.
 */
trait SaveTrait
{
    protected QueryBuilderInterface $saveQueryBuilder;

    /**
     * Get MongoDB collection object.
     */
    abstract public function getStorage(): Collection;

    /**
     * Get the result builder.
     */
    abstract public function getResultBuilder(): ResultBuilder;


    /**
     * Get the query builder for saving new items
     *
     * @return QueryBuilderInterface|StagedQueryBuilder
     */
    public function getSaveQueryBuilder(): QueryBuilderInterface
    {
        $this->saveQueryBuilder ??= new SaveQueryBuilder();

        return $this->saveQueryBuilder;
    }

    /**
     * Create a write with a custom query builder for save.
     *
     * @param QueryBuilderInterface $builder
     * @return static
     */
    public function withSaveQueryBuilder(QueryBuilderInterface $builder): self
    {
        return $this->with('saveQueryBuilder', $builder);
    }


    /**
     * Save the one item.
     * Returns a result with the generated id.
     *
     * @param object|array $item
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function save($item, array $opts = []): Result
    {
        return $this->saveAll([$item], $opts);
    }

    /**
     * Save multiple items.
     * Returns a result with the generated ids.
     *
     * @param iterable          $items
     * @param OptionInterface[] $opts
     * @return Result
     * @throws BuildQueryException
     */
    public function saveAll(iterable $items, array $opts = []): Result
    {
        $query = new WriteQuery(['ordered' => false]);
        $this->getSaveQueryBuilder()->apply($query, $items, $opts);

        $query->expectMethods('insertOne', 'replaceOne', 'updateOne');
        $mongoOperations = $query->getOperations();
        $mongoOptions = $query->getOptions();

        $this->debug("%s.bulkWrite", ['operations' => $mongoOperations, 'options' => $mongoOptions]);

        $writeResult = $this->getStorage()->bulkWrite($mongoOperations, $mongoOptions);

        return $this->createSaveResult($query->getIndex(), $writeResult);
    }

    /**
     * Aggregate the meta from multiple bulk write actions
     *
     * @param array           $index
     * @param BulkWriteResult $writeResult
     * @return Result
     */
    protected function createSaveResult(array $index, BulkWriteResult $writeResult): Result
    {
        $meta = [];

        if ($writeResult->isAcknowledged()) {
            $meta['count'] = $writeResult->getInsertedCount() + (int)$writeResult->getModifiedCount()
                + $writeResult->getUpsertedCount();
            $meta['matched'] = $writeResult->getMatchedCount();
            $meta['inserted'] = $writeResult->getInsertedCount();
            $meta['modified'] = $writeResult->getModifiedCount();
        }

        $ids = $writeResult->getInsertedIds()
            + $writeResult->getUpsertedIds()
            + array_fill(0, count($index), null);
        $documents = i\iterable_map($ids, fn($id) => ($id === null ? [] : ['_id' => $id]));

        return $this->getResultBuilder()
            ->with($documents, $meta)
            ->setKeys($index);
    }
}
