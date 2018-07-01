<?php


namespace HrefTypeaheadBundle\Service;


use Pimcore\Model\User;

class SearchBuilder
{
    /** @var  array */
    private $types;
    /** @var  array */
    private $subTypes;
    /** @var  User */
    private $user;
    /** @var  array */
    private $classNames;
    /** @var  string */
    private $query;
    /** @var  int */
    private $start;
    /** @var  int */
    private $limit;
    /** @var  array */
    private $fields;
    /** @var array */
    private $filter;
    /** @var  array */
    private $tagIds;
    /** @var  bool */
    private $considerChildTags;
    /** @var array */
    private $sortSettings;

    /**
     * @return SearchBuilder
     */
    public static function create()
    {
        return new self();
    }

    /**
     * @return SearchService
     */
    public function build()
    {
        return new SearchService($this);
    }

    /**
     * @return array
     */
    public function getTypes()
    {
        return $this->types;
    }

    /**
     * @param array $types
     * @return SearchBuilder
     */
    public function withTypes(array $types)
    {
        $this->types = $types;

        return $this;
    }

    /**
     * @return array
     */
    public function getSubTypes()
    {
        return $this->subTypes;
    }

    /**
     * @param array $subTypes
     * @return SearchBuilder
     */
    public function withSubTypes(array $subTypes)
    {
        $this->subTypes = $subTypes;

        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     * @return SearchBuilder
     */
    public function withUser(User $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return array
     */
    public function getClassNames()
    {
        return $this->classNames;
    }

    /**
     * @param array $classNames
     * @return SearchBuilder
     */
    public function withClassNames($classNames)
    {
        $this->classNames = $classNames;

        return $this;
    }

    /**
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param string $query
     * @return SearchBuilder
     */
    public function withQuery($query)
    {
        $this->query = $query;

        return $this;
    }

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param int $start
     * @return SearchBuilder
     */
    public function withStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return SearchBuilder
     */
    public function withLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @return array
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param array|null $fields
     * @return SearchBuilder
     */
    public function withFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * @return array
     */
    public function getFilter()
    {
        return $this->filter;
    }

    /**
     * @param array|null $filter
     * @return SearchBuilder
     */
    public function withFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * @return array
     */
    public function getTagIds()
    {
        return $this->tagIds;
    }

    /**
     * @param array|null $tagIds
     * @return SearchBuilder
     */
    public function withTagIds($tagIds)
    {
        $this->tagIds = $tagIds;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isConsiderChildTags()
    {
        return $this->considerChildTags;
    }

    /**
     * @param boolean $considerChildTags
     * @return SearchBuilder
     */
    public function withConsiderChildTags($considerChildTags)
    {
        $this->considerChildTags = $considerChildTags;

        return $this;
    }

    /**
     * @return array
     */
    public function getSortSettings()
    {
        return $this->sortSettings;
    }

    /**
     * @param array $sortSettings
     * @return $this
     */
    public function withSortSettings($sortSettings)
    {
        $this->sortSettings = $sortSettings;
        return $this;
    }

}
