<?php

namespace JiraRestApi\Issue;

class Comment implements \JsonSerializable
{
    use VisibilityTrait;

    /** @var string */
    public $self;

    /** @var string */
    public $id;

    /** @var \JiraRestApi\Issue\Reporter */
    public $author;

    /** @var string */
    public $body;

    /** @var string */
    public $renderedBody;

    /** @var \JiraRestApi\Issue\Reporter */
    public $updateAuthor;

    /** @var \DateTimeInterface */
    public $created;

    /** @var \DateTimeInterface */
    public $updated;

    /** @var \JiraRestApi\Issue\Visibility */
    public $visibility;

    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this));
    }
}
