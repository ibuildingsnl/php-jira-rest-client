<?php

namespace JiraRestApi\Issue;

use ArrayObject;
use JiraRestApi\JiraException;
use JiraRestApi\Project\ProjectService;

class IssueService extends \JiraRestApi\JiraClient
{
    private $uri = '/issue';

    /**
     * @param object $json
     *
     * @throws \JsonMapper_Exception
     *
     * @return Issue
     */
    public function getIssueFromJSON($json): Issue
    {
        $issue = $this->json_mapper->map(
            $json,
            new Issue()
        );

        return $issue;
    }

    /**
     *  get all project list.
     *
     * @param string|int $issueIdOrKey
     * @param array      $paramArray   Query Parameter key-value Array.
     * @param Issue      $issueObject
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Issue class
     */
    public function get($issueIdOrKey, $paramArray = [], $issueObject = null): Issue
    {
        $issueObject = ($issueObject) ? $issueObject : new Issue();

        $ret = $this->exec($this->uri.'/'.$issueIdOrKey.$this->toHttpQueryParameter($paramArray), null);

        $this->log->info("Result=\n".$ret);

        return $issue = $this->json_mapper->map(
            json_decode($ret),
            $issueObject
        );
    }

    /**
     * create new issue.
     *
     * @param IssueField $issueField
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Issue created issue key
     */
    public function create($issueField): Issue
    {
        $issue = new Issue();

        // serilize only not null field.
        $issue->fields = $issueField;

        $data = json_encode($issue);

        $this->log->info("Create Issue=\n".$data);

        $ret = $this->exec($this->uri, $data, 'POST');

        return $this->getIssueFromJSON(json_decode($ret));
    }

    /**
     * Create multiple issues using bulk insert.
     *
     * @param IssueField[] $issueFields Array of IssueField objects
     * @param int          $batchSize   Maximum number of issues to send in each request
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Issue[] Array of results, where each result represents one batch of insertions
     */
    public function createMultiple($issueFields, $batchSize = 50): array
    {
        $issues = [];

        foreach ($issueFields as $issueField) {
            $issue = new Issue();
            $issue->fields = $issueField;
            $issues[] = $issue;
        }

        $batches = array_chunk($issues, $batchSize);

        $results = [];
        foreach ($batches as $batch) {
            $results = array_merge($results, $this->bulkInsert($batch));
        }

        return $results;
    }

    /**
     * Makes API call to bulk insert issues.
     *
     * @param Issue[] $issues Array of issue arrays that are sent to Jira one by one in single create
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Issue[] Result of API call to insert many issues
     */
    private function bulkInsert($issues): array
    {
        $data = json_encode(['issueUpdates' => $issues]);

        $this->log->info("Create Issues=\n".$data);
        $results = $this->exec($this->uri.'/bulk', $data, 'POST');

        $issues = [];
        foreach (json_decode($results)->issues as $result) {
            $issues[] = $this->getIssueFromJSON($result);
        }

        return $issues;
    }

    /**
     * Add one or more file to an issue.
     *
     * @param string|int   $issueIdOrKey  Issue id or key
     * @param array|string $filePathArray attachment file path.
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Attachment[]
     */
    public function addAttachments($issueIdOrKey, $filePathArray): array
    {
        if (is_array($filePathArray) == false) {
            $filePathArray = [$filePathArray];
        }

        $results = $this->upload($this->uri."/$issueIdOrKey/attachments", $filePathArray);

        $this->log->info('addAttachments result='.var_export($results, true));

        $attachArr = [];
        foreach ($results as $ret) {
            $ret = json_decode($ret);
            if (is_array($ret)) {
                $tmpArr = $this->json_mapper->mapArray(
                    $ret,
                    new \ArrayObject(),
                    Attachment::class
                );

                foreach ($tmpArr as $t) {
                    $attachArr[] = $t;
                }
            } elseif (is_object($ret)) {
                $attachArr[] = $this->json_mapper->map(
                    $ret,
                    new Attachment()
                );
            }
        }

        return $attachArr;
    }

    /**
     * update issue.
     *
     * @param string|int $issueIdOrKey Issue Key
     * @param IssueField $issueField   object of Issue class
     * @param array      $paramArray   Query Parameter key-value Array.
     *
     * @throws JiraException
     *
     * @return string created issue key
     */
    public function update($issueIdOrKey, $issueField, $paramArray = []): string
    {
        $issue = new Issue();

        // serilize only not null field.
        $issue->fields = $issueField;

        //$issue = $this->filterNullVariable((array)$issue);

        $data = json_encode($issue);

        $this->log->info("Update Issue=\n".$data);

        $queryParam = '?'.http_build_query($paramArray);

        $ret = $this->exec($this->uri."/$issueIdOrKey".$queryParam, $data, 'PUT');

        return $ret;
    }

    /**
     * Adds a new comment to an issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param Comment    $comment
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Comment Comment class
     */
    public function addComment($issueIdOrKey, $comment): Comment
    {
        $this->log->info("addComment=\n");

        if (!($comment instanceof Comment) || empty($comment->body)) {
            throw new JiraException('comment param must be instance of Comment and have body text.');
        }

        $data = json_encode($comment);

        $ret = $this->exec($this->uri."/$issueIdOrKey/comment", $data);

        $this->log->debug('add comment result='.var_export($ret, true));
        $comment = $this->json_mapper->map(
            json_decode($ret),
            new Comment()
        );

        return $comment;
    }

    /**
     * Update a comment in issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param string|int $id           Comment id
     * @param Comment    $comment
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Comment Comment class
     */
    public function updateComment($issueIdOrKey, $id, $comment): Comment
    {
        $this->log->info("updateComment=\n");

        if (!($comment instanceof Comment) || empty($comment->body)) {
            throw new JiraException('comment param must instance of Comment and have to body text.!');
        }

        $data = json_encode($comment);

        $ret = $this->exec($this->uri."/$issueIdOrKey/comment/$id", $data, 'PUT');

        $this->log->debug('update comment result='.var_export($ret, true));
        $comment = $this->json_mapper->map(
            json_decode($ret),
            new Comment()
        );

        return $comment;
    }

    /**
     * Get a comment on an issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param string|int $id           Comment id
     * @param array      $paramArray   query parameter
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Comment Comment class
     */
    public function getComment($issueIdOrKey, $id, array $paramArray = []): Comment
    {
        $this->log->info("getComment=\n");

        $ret = $this->exec($this->uri."/$issueIdOrKey/comment/$id".$this->toHttpQueryParameter($paramArray));

        $this->log->debug('get comment result='.var_export($ret, true));
        $comment = $this->json_mapper->map(
            json_decode($ret),
            new Comment()
        );

        return $comment;
    }

    /**
     * Get all comments on an issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param array      $paramArray   Query Parameter key-value Array.
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Comments Comments class
     */
    public function getComments($issueIdOrKey, array $paramArray = []): Comments
    {
        $this->log->info("getComments=\n");

        $ret = $this->exec($this->uri.'/'.$issueIdOrKey.'/comment'.$this->toHttpQueryParameter($paramArray), null);

        $this->log->debug('get comments result='.var_export($ret, true));
        $comments = $this->json_mapper->map(
            json_decode($ret),
            new Comments()
        );

        return $comments;
    }

    /**
     * Delete a comment on an issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param string|int $id           Comment id
     *
     * @throws JiraException
     *
     * @return string|bool
     */
    public function deleteComment($issueIdOrKey, $id): string|bool
    {
        $this->log->info("deleteComment=\n");

        $ret = $this->exec($this->uri."/$issueIdOrKey/comment/$id", '', 'DELETE');

        $this->log->info('delete comment '.$issueIdOrKey.' '.$id.' result='.var_export($ret, true));

        return $ret;
    }

    /**
     * Change a issue assignee.
     *
     * @param string|int  $issueIdOrKey
     * @param string|null $assigneeName Assigns an issue to a user.
     *                                  If the assigneeName is "-1" automatic assignee is used.
     *                                  A null name will remove the assignee.
     *
     * @throws JiraException
     *
     * @return string|bool
     */
    public function changeAssignee($issueIdOrKey, $assigneeName): string|bool
    {
        $this->log->info("changeAssignee=\n");

        $ar = ['name' => $assigneeName];

        $data = json_encode($ar);

        $ret = $this->exec($this->uri."/$issueIdOrKey/assignee", $data, 'PUT');

        $this->log->info('change assignee of '.$issueIdOrKey.' to '.$assigneeName.' result='.var_export($ret, true));

        return $ret;
    }

    /**
     * Change a issue assignee for REST API V3.
     *
     * @param string|int  $issueIdOrKey
     * @param string|null $accountId    Assigns an issue to a user.
     *
     * @throws JiraException
     *
     * @return string
     */
    public function changeAssigneeByAccountId($issueIdOrKey, $accountId): string
    {
        $this->log->info("changeAssigneeByAccountId=\n");

        $ar = ['accountId' => $accountId];

        $data = json_encode($ar);

        $ret = $this->exec($this->uri."/$issueIdOrKey/assignee", $data, 'PUT');

        $this->log->info('change assignee of '.$issueIdOrKey.' to '.$accountId.' result='.var_export($ret, true));

        return $ret;
    }

    /**
     * Delete a issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param array      $paramArray   Query Parameter key-value Array.
     *
     * @throws JiraException
     *
     * @return string|bool
     */
    public function deleteIssue($issueIdOrKey, $paramArray = []): string|bool
    {
        $this->log->info("deleteIssue=\n");

        $queryParam = '?'.http_build_query($paramArray);

        $ret = $this->exec($this->uri."/$issueIdOrKey".$queryParam, '', 'DELETE');

        $this->log->info('delete issue '.$issueIdOrKey.' result='.var_export($ret, true));

        return $ret;
    }

    /**
     * Get a list of the transitions possible for this issue by the current user, along with fields that are required and their types.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param array      $paramArray   Query Parameter key-value Array.
     *
     * @throws JiraException
     *
     * @return Transition[] array of Transition class
     */
    public function getTransition($issueIdOrKey, $paramArray = []): ArrayObject
    {
        $queryParam = '?'.http_build_query($paramArray);

        $ret = $this->exec($this->uri."/$issueIdOrKey/transitions".$queryParam);

        $this->log->debug('getTransitions result='.var_export($ret, true));

        $data = json_encode(json_decode($ret)->transitions);

        $transitions = $this->json_mapper->mapArray(
            json_decode($data),
            new \ArrayObject(),
            Transition::class
        );

        return $transitions;
    }

    /**
     * find transition id by transition's to field name(aka 'Resolved').
     *
     * @param string|int $issueIdOrKey
     * @param string     $transitionToName
     *
     * @throws JiraException
     *
     * @return string
     */
    public function findTransitonId($issueIdOrKey, $transitionToName): string
    {
        $this->log->debug('findTransitonId=');

        $ret = $this->getTransition($issueIdOrKey);

        foreach ($ret as $trans) {
            $toName = $trans->to->name;

            $this->log->debug('getTransitions result='.var_export($ret, true));

            if (strcasecmp($toName, $transitionToName) === 0) {
                return $trans->id;
            }
        }

        // transition keyword not found
        throw new JiraException("Transition name '$transitionToName' not found on JIRA Server.");
    }

    /**
     * Perform a transition on an issue.
     *
     * @param string|int $issueIdOrKey Issue id or key
     * @param Transition $transition
     *
     * @throws JiraException
     *
     * @return string|null nothing - if transition was successful return http 204(no contents)
     */
    public function transition($issueIdOrKey, $transition): ?string
    {
        $this->log->debug('transition='.var_export($transition, true));

        if (!isset($transition->transition['id'])) {
            if (isset($transition->transition['untranslatedName'])) {
                $transition->transition['id'] = $this->findTransitonIdByUntranslatedName($issueIdOrKey, $transition->transition['untranslatedName']);
            } elseif (isset($transition->transition['name'])) {
                $transition->transition['id'] = $this->findTransitonId($issueIdOrKey, $transition->transition['name']);
            } else {
                throw new JiraException('you must set either name or untranslatedName for performing transition.');
            }
        }

        $data = json_encode($transition);

        $this->log->debug("transition req=$data\n");

        $ret = $this->exec($this->uri."/$issueIdOrKey/transitions", $data, 'POST');

        $this->log->debug('getTransitions result='.var_export($ret, true));

        return $ret;
    }

    /**
     * Search issues.
     *
     * @param string $jql
     * @param int    $startAt
     * @param int    $maxResults
     * @param array  $fields
     * @param array  $expand
     * @param bool   $validateQuery
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return IssueSearchResult
     */
    public function search(string $jql, int $startAt = 0, int $maxResults = 15, array $fields = [], array $expand = [], bool $validateQuery = true): IssueSearchResult
    {
        $data = json_encode([
            'jql'           => $jql,
            'startAt'       => $startAt,
            'maxResults'    => $maxResults,
            'fields'        => $fields,
            'expand'        => $expand,
            'validateQuery' => $validateQuery,
        ]);

        $ret = $this->exec('search', $data, 'POST');
        $json = json_decode($ret);

        $result = null;

        $result = $this->json_mapper->map(
            $json,
            new IssueSearchResult()
        );

        return $result;
    }

    /**
     * get TimeTracking info.
     *
     * @param string $issueIdOrKey
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return TimeTracking
     */
    public function getTimeTracking(string $issueIdOrKey): TimeTracking
    {
        $ret = $this->exec($this->uri."/$issueIdOrKey", null);
        $this->log->debug("getTimeTracking res=$ret\n");

        $issue = $this->json_mapper->map(
            json_decode($ret),
            new Issue()
        );

        return $issue->fields->timeTracking;
    }

    /**
     * TimeTracking issues.
     *
     * @param string       $issueIdOrKey Issue id or key
     * @param TimeTracking $timeTracking
     *
     * @throws JiraException
     *
     * @return string
     */
    public function timeTracking(string $issueIdOrKey, TimeTracking $timeTracking): string
    {
        $array = [
            'update' => [
                'timetracking' => [
                    ['edit' => $timeTracking],
                ],
            ],
        ];

        $data = json_encode($array);

        $this->log->debug("TimeTracking req=$data\n");

        // if success, just return HTTP 201.
        return $this->exec($this->uri."/$issueIdOrKey", $data, 'PUT');
    }

    /**
     * get property.
     *
     * @param string   $issueIdOrKey
     * @param Property $property
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return void
     */
    public function setProperty(string $issueIdOrKey, Property $property)
    {
        $this->log->info("setProperty=\n");

        $data = json_encode($property->value);
        $url = $this->uri."/$issueIdOrKey/properties/".$property->key;
        $type = 'PUT';

        $ret = $this->exec($url, $data, $type);
    }

    /**
     * get property.
     *
     * @param string $issueIdOrKey
     * @param string $property     the name of the property, e.g.com.railsware.SmartChecklist.checklist
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return Property
     */
    public function getProperty(string $issueIdOrKey, $property): Property
    {
        $ret = $this->exec($this->uri."/$issueIdOrKey/properties/".$property);

        $this->log->debug("getProperty res=$ret\n");

        return $this->json_mapper->map(
            json_decode($ret),
            new Property()
        );
    }

    /**
     * get getWorklog.
     *
     * @param string $issueIdOrKey
     * @param array  $paramArray   Possible keys for $paramArray: 'startAt', 'maxResults', 'startedAfter', 'expand'
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return PaginatedWorklog
     */
    public function getWorklog(string $issueIdOrKey, array $paramArray = []): PaginatedWorklog
    {
        $ret = $this->exec($this->uri."/$issueIdOrKey/worklog".$this->toHttpQueryParameter($paramArray));

        $this->log->debug("getWorklog res=$ret\n");

        return $this->json_mapper->map(
            json_decode($ret),
            new PaginatedWorklog()
        );
    }

    /**
     * get getWorklog by Id.
     *
     * @param string $issueIdOrKey
     * @param int    $workLogId
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return Worklog PaginatedWorklog object
     */
    public function getWorklogById(string $issueIdOrKey, int $workLogId): Worklog
    {
        $ret = $this->exec($this->uri."/$issueIdOrKey/worklog/$workLogId");

        $this->log->debug("getWorklogById res=$ret\n");

        return $this->json_mapper->map(
            json_decode($ret),
            new Worklog()
        );
    }

    /**
     * @param array<int> $ids
     *
     * @return array<Worklog>
     */
    public function getWorklogsByIds(array $ids): array
    {
        $ret = $this->exec('/worklog/list', json_encode(['ids' => $ids]), 'POST');

        $this->log->debug("getWorklogsByIds res=$ret\n");

        $worklogsResponse = json_decode($ret, false, 512, JSON_THROW_ON_ERROR);

        $worklogs = array_map(function ($worklog) {
            return $this->json_mapper->map($worklog, new Worklog());
        }, $worklogsResponse);

        return $worklogs;
    }

    /**
     * add work log to issue.
     *
     * @param string  $issueIdOrKey
     * @param Worklog $worklog
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return Worklog Worklog Object
     */
    public function addWorklog(string $issueIdOrKey, Worklog $worklog)
    {
        $this->log->info("addWorklog=\n");

        $data = json_encode($worklog);
        $url = $this->uri."/$issueIdOrKey/worklog";
        $type = 'POST';

        $ret = $this->exec($url, $data, $type);

        return $this->json_mapper->map(
            json_decode($ret),
            new Worklog()
        );
    }

    /**
     * edit the worklog.
     *
     * @param string  $issueIdOrKey
     * @param Worklog $worklog
     * @param int     $worklogId
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return Worklog
     */
    public function editWorklog(string $issueIdOrKey, Worklog $worklog, int $worklogId): Worklog
    {
        $this->log->info("editWorklog=\n");

        $data = json_encode($worklog);
        $url = $this->uri."/$issueIdOrKey/worklog/$worklogId";
        $type = 'PUT';

        $ret = $this->exec($url, $data, $type);

        return $this->json_mapper->map(
            json_decode($ret),
            new Worklog()
        );
    }

    /**
     * delete worklog.
     *
     * @param string $issueIdOrKey
     * @param int    $worklogId
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function deleteWorklog(string $issueIdOrKey, int $worklogId): bool
    {
        $this->log->info("deleteWorklog=\n");

        $url = $this->uri."/$issueIdOrKey/worklog/$worklogId";
        $type = 'DELETE';

        $ret = $this->exec($url, null, $type);

        return (bool) $ret;
    }

    /**
     * Get all priorities.
     *
     * @throws JiraException
     *
     * @return Priority[] array of priority class
     */
    public function getAllPriorities(): ArrayObject
    {
        $ret = $this->exec('priority', null);

        return $this->json_mapper->mapArray(
            json_decode($ret, false),
            new \ArrayObject(),
            Priority::class
        );
    }

    /**
     * Get priority by id.
     * throws  HTTPException if the priority is not found, or the calling user does not have permission or view it.
     *
     * @param int $priorityId Id of priority.
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return Priority priority
     */
    public function getPriority(int $priorityId): Priority
    {
        $ret = $this->exec("priority/$priorityId", null);

        $this->log->info('Result='.$ret);

        return $this->json_mapper->map(
            json_decode($ret),
            new Priority()
        );
    }

    /**
     * Get priority by id.
     * throws HTTPException if the priority is not found, or the calling user does not have permission or view it.
     *
     * @param $paramArray array parameters
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return CustomFieldSearchResult array of CustomeFiled
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/9.14.0/#api/2/customFields-getCustomFields
     */
    public function getCustomFields(array $paramArray = []): CustomFieldSearchResult
    {
        $ret = $this->exec('customFields'.$this->toHttpQueryParameter($paramArray), null);

        $this->log->info('Result='.$ret);

        //\JiraRestApi\Dumper::dd(json_decode($ret, false));

        return $this->json_mapper->map(
            json_decode($ret, false),
            new CustomFieldSearchResult()
        );
    }

    /**
     * get watchers.
     *
     * @param string $issueIdOrKey
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return Reporter[]
     */
    public function getWatchers(string $issueIdOrKey): ArrayObject
    {
        $this->log->info("getWatchers=\n");

        $url = $this->uri."/$issueIdOrKey/watchers";

        $ret = $this->exec($url, null);

        return $this->json_mapper->mapArray(
            json_decode($ret, false)->watchers,
            new \ArrayObject(),
            Reporter::class
        );
    }

    /**
     * add watcher to issue.
     *
     * @param string $issueIdOrKey
     * @param string $watcher      watcher id
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function addWatcher(string $issueIdOrKey, string $watcher): bool
    {
        $this->log->info("addWatcher=\n");

        $data = json_encode($watcher);
        $url = $this->uri."/$issueIdOrKey/watchers";
        $type = 'POST';

        $this->exec($url, $data, $type);

        return $this->http_response == 204 ? true : false;
    }

    /**
     * remove watcher from issue.
     *
     * @param string $issueIdOrKey
     * @param string $watcher      watcher id
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function removeWatcher(string $issueIdOrKey, string $watcher): bool
    {
        $this->log->debug("removeWatcher=\n");

        $ret = $this->exec($this->uri."/$issueIdOrKey/watchers/?username=$watcher", '', 'DELETE');

        $this->log->debug('remove watcher '.$issueIdOrKey.' result='.var_export($ret, true));

        return $this->http_response == 204 ? true : false;
    }

    /**
     * remove watcher from issue by watcher account id.
     *
     * @param string $issueIdOrKey
     * @param string $accountId    Watcher account id.
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function removeWatcherByAccountId(string $issueIdOrKey, string $accountId): bool
    {
        $this->log->debug("removeWatcher=\n");

        $ret = $this->exec($this->uri."/$issueIdOrKey/watchers/?accountId=$accountId", '', 'DELETE');

        $this->log->debug('remove watcher '.$issueIdOrKey.' result='.var_export($ret, true));

        return $this->http_response == 204 ? true : false;
    }

    /**
     * Get the meta data for creating issues.
     *
     * @param array $paramArray Possible keys for $paramArray: 'projectIds', 'projectKeys', 'issuetypeIds', 'issuetypeNames'.
     * @param bool  $expand     Retrieve all issue fields and values
     *
     * @throws JiraException
     *
     * @return object object of meta data for creating issues.
     */
    public function getCreateMeta(array $paramArray = [], bool $expand = true): object
    {
        $paramArray['expand'] = ($expand) ? 'projects.issuetypes.fields' : null;
        $paramArray = array_filter($paramArray);

        $queryParam = '?'.http_build_query($paramArray);

        $ret = $this->exec($this->uri.'/createmeta'.$queryParam, null);

        return json_decode($ret);
    }

    /**
     * returns the metadata(include custom field) for an issue.
     *
     * @param string $idOrKey                issue id or key
     * @param bool   $overrideEditableFlag   Allows retrieving edit metadata for fields in non-editable status
     * @param bool   $overrideScreenSecurity Allows retrieving edit metadata for the fields hidden on Edit screen.
     *
     * @throws JiraException
     *
     * @return array of custom fields
     *
     * @see https://confluence.atlassian.com/jirakb/how-to-retrieve-available-options-for-a-multi-select-customfield-via-jira-rest-api-815566715.html How to retrieve available options for a multi-select customfield via JIRA REST API
     * @see https://developer.atlassian.com/cloud/jira/platform/rest/#api-api-2-issue-issueIdOrKey-editmeta-get
     */
    public function getEditMeta(string $idOrKey, bool $overrideEditableFlag = false, bool $overrideScreenSecurity = false): array
    {
        $queryParam = '?'.http_build_query([
            'overrideEditableFlag'   => $overrideEditableFlag,
            'overrideScreenSecurity' => $overrideScreenSecurity,
        ]);

        $uri = sprintf('%s/%s/editmeta', $this->uri, $idOrKey).$queryParam;

        $ret = $this->exec($uri, null);

        $metas = json_decode($ret, true);

        // extract only custom field(startWith customefield_XXXXX)
        $cfs = array_filter($metas['fields'], function ($key) {
            $pos = strpos($key, 'customfield');

            return $pos !== false;
        }, ARRAY_FILTER_USE_KEY);

        return $cfs;
    }

    /**
     * Sends a notification (email) to the list or recipients defined in the request.
     *
     * @param string $issueIdOrKey Issue id Or Key
     * @param Notify $notify
     *
     * @throws JiraException
     *
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/latest/#api/2/issue-notify
     */
    public function notify(string $issueIdOrKey, Notify $notify)
    {
        $full_uri = $this->uri."/$issueIdOrKey/notify";

        // set self value
        foreach ($notify->to['groups'] as &$g) {
            $g['self'] = $this->getConfiguration()->getJiraHost().'/rest/api/2/group?groupname='.$g['name'];
        }
        foreach ($notify->restrict['groups'] as &$g) {
            $g['self'] = $this->getConfiguration()->getJiraHost().'/rest/api/2/group?groupname='.$g['name'];
        }

        $data = json_encode($notify, JSON_UNESCAPED_SLASHES);

        $this->log->debug("notify=$data\n");

        $ret = $this->exec($full_uri, $data, 'POST');

        if ($ret !== true) {
            throw new JiraException('notify failed: response code='.$ret);
        }
    }

    /**
     * Get all remote issue links on the issue.
     *
     * @param string $issueIdOrKey Issue id Or Key
     *
     * @throws JiraException
     *
     * @return RemoteIssueLink[]
     *
     * @see https://developer.atlassian.com/server/jira/platform/jira-rest-api-for-remote-issue-links/
     * @see https://docs.atlassian.com/software/jira/docs/api/REST/latest/#api/2/issue-getRemoteIssueLinks
     */
    public function getRemoteIssueLink(string $issueIdOrKey): ArrayObject
    {
        $full_uri = $this->uri."/$issueIdOrKey/remotelink";

        $ret = $this->exec($full_uri, null);

        $rils = $this->json_mapper->mapArray(
            json_decode($ret, false),
            new \ArrayObject(),
            RemoteIssueLink::class
        );

        return $rils;
    }

    /**
     * @param string          $issueIdOrKey
     * @param RemoteIssueLink $ril
     *
     * @throws \JsonMapper_Exception
     * @throws JiraException
     *
     * @return RemoteIssueLink
     */
    public function createOrUpdateRemoteIssueLink(string $issueIdOrKey, RemoteIssueLink $ril): RemoteIssueLink
    {
        $full_uri = $this->uri."/$issueIdOrKey/remotelink";

        $data = json_encode($ril, JSON_UNESCAPED_SLASHES);

        $this->log->debug("create remoteIssueLink=$data\n");

        $ret = $this->exec($full_uri, $data, 'POST');

        $res = $this->json_mapper->map(
            json_decode($ret),
            new RemoteIssueLink()
        );

        return $res;
    }

    /**
     * @param string $issueIdOrKey
     * @param string $globalId
     *
     * @throws JiraException
     *
     * @return string|bool
     */
    public function removeRemoteIssueLink(string $issueIdOrKey, string $globalId): string|bool
    {
        $query = http_build_query(['globalId' => $globalId]);

        $full_uri = sprintf('%s/%s/remotelink?%s', $this->uri, $issueIdOrKey, $query);

        $ret = $this->exec($full_uri, '', 'DELETE');

        $this->log->info(
            sprintf(
                'delete remote issue link for issue "%s" with globalId "%s". Result=%s',
                $issueIdOrKey,
                $globalId,
                var_export($ret, true)
            )
        );

        return $ret;
    }

    /**
     * get all issue security schemes.
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return SecurityScheme[] array of SecurityScheme class
     */
    public function getAllIssueSecuritySchemes(): ArrayObject
    {
        $url = '/issuesecurityschemes';

        $ret = $this->exec($url);

        $data = json_decode($ret, true);

        // extract schem field
        $schemes = json_decode(json_encode($data['issueSecuritySchemes']), false);

        $res = $this->json_mapper->mapArray(
            $schemes,
            new \ArrayObject(),
            SecurityScheme::class
        );

        return $res;
    }

    /**
     *  get issue security scheme.
     *
     * @param string $securityId security scheme id
     *
     * @throws JiraException
     * @throws \JsonMapper_Exception
     *
     * @return SecurityScheme SecurityScheme
     */
    public function getIssueSecuritySchemes(string $securityId): SecurityScheme
    {
        $url = '/issuesecurityschemes/'.$securityId;

        $ret = $this->exec($url);

        $res = $this->json_mapper->map(
            json_decode($ret),
            new SecurityScheme()
        );

        return $res;
    }

    /**
     * convenient wrapper function for add or remove labels.
     *
     * @param string $issueIdOrKey
     * @param array  $addLablesParam
     * @param array  $removeLabelsParam
     * @param bool   $notifyUsers
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function updateLabels(string $issueIdOrKey, array $addLablesParam = [], array $removeLabelsParam = [], bool $notifyUsers = true): bool
    {
        $labels = [];
        if (count($addLablesParam) > 0) {
            foreach ($addLablesParam as $a) {
                $labels[] = ['add' => $a];
            }
        }

        if (count($removeLabelsParam) > 0) {
            foreach ($removeLabelsParam as $r) {
                $labels[] = ['remove' => $r];
            }
        }

        $postData = json_encode([
            'update' => [
                'labels' => $labels,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->log->info("Update labels=\n".$postData);

        $queryParam = '';
        if (!$notifyUsers) {
            $queryParam = '?'.http_build_query(['notifyUsers' => 'false']);
        }

        $ret = $this->exec($this->uri."/$issueIdOrKey".$queryParam, $postData, 'PUT');

        return $ret;
    }

    /**
     * convenient wrapper function for add or remove fix versions.
     *
     * @param string $issueIdOrKey
     * @param array  $addFixVersionsParam
     * @param array  $removeFixVersionsParam
     * @param bool   $notifyUsers
     *
     * @throws JiraException
     *
     * @return bool
     */
    public function updateFixVersions(string $issueIdOrKey, array $addFixVersionsParam, array $removeFixVersionsParam, bool $notifyUsers = true): bool
    {
        $fixVersions = [];
        if (count($addFixVersionsParam) > 0) {
            foreach ($addFixVersionsParam as $a) {
                $fixVersions[] = ['add' => ['name' => $a]];
            }
        }

        if (count($removeFixVersionsParam) > 0) {
            foreach ($removeFixVersionsParam as $r) {
                $fixVersions[] = ['remove' => ['name' => $r]];
            }
        }

        $postData = json_encode([
            'update' => [
                'fixVersions' => $fixVersions,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->log->info("Update fixVersions=\n".$postData);

        $queryParam = '?'.http_build_query(['notifyUsers' => $notifyUsers]);

        $ret = $this->exec($this->uri."/$issueIdOrKey".$queryParam, $postData, 'PUT');

        return $ret;
    }

    /**
     * find transition id by transition's untranslatedName.
     *
     * @param string $issueIdOrKey
     * @param string $untranslatedName
     *
     * @throws JiraException
     *
     * @return string
     */
    public function findTransitonIdByUntranslatedName(string $issueIdOrKey, string $untranslatedName): string
    {
        $this->log->debug('findTransitonIdByUntranslatedName=');

        $prj = new ProjectService($this->getConfiguration());
        $pkey = explode('-', $issueIdOrKey);
        $transitionArray = $prj->getProjectTransitionsToArray($pkey[0]);

        $this->log->debug('getTransitions result='.var_export($transitionArray, true));

        foreach ($transitionArray as $trans) {
            if (strcasecmp($trans['name'], $untranslatedName) === 0 ||
                strcasecmp($trans['untranslatedName'] ?? '', $untranslatedName) === 0) {
                return $trans['id'];
            }
        }

        // transition keyword not found
        throw new JiraException("Transition name '$untranslatedName' not found on JIRA Server.");
    }
}
