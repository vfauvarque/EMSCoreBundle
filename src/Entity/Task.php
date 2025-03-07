<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use EMS\CommonBundle\Common\Standard\DateTime;
use EMS\CoreBundle\Core\Revision\Task\TaskDTO;
use EMS\CoreBundle\Core\Revision\Task\TaskLog;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @ORM\Table(name="task")
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 *
 * @final
 */
class Task implements EntityInterface
{
    /**
     * @ORM\Id
     * @ORM\Column(type="uuid", unique=true)
     */
    private UuidInterface $id;

    /**
     * @ORM\Column(name="title", type="string", length=255)
     */
    private string $title;

    /**
     * @ORM\Column(name="status", type="string", length=25)
     */
    private string $status;

    /**
     * @ORM\Column(name="deadline", type="datetime_immutable", nullable=true)
     */
    private ?\DateTimeInterface $deadline = null;

    /**
     * @ORM\Column(name="assignee", type="text")
     */
    private string $assignee;

    /**
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @var array<mixed>
     *
     * @ORM\Column(name="logs", type="json", nullable=false)
     */
    private array $logs;

    public const STATUS_PROGRESS = 'progress';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';

    public const STYLES = [
        self::STATUS_PLANNED => ['icon' => 'fa fa-hourglass-o', 'bg' => 'gray', 'text' => 'muted', 'label' => 'default'],
        self::STATUS_PROGRESS => ['icon' => 'fa fa-ticket', 'bg' => 'blue', 'text' => 'primary', 'label' => 'primary'],
        self::STATUS_COMPLETED => ['icon' => 'fa fa-paper-plane', 'bg' => 'green', 'text' => 'success', 'label' => 'success'],
        self::STATUS_REJECTED => ['icon' => 'fa fa-close', 'bg' => 'red', 'text' => 'danger', 'label' => 'danger'],
        self::STATUS_APPROVED => ['icon' => 'fa fa-check', 'bg' => 'green', 'text' => 'success', 'label' => 'success'],
    ];

    public function __construct()
    {
        $this->id = Uuid::uuid4();
        $this->status = self::STATUS_PLANNED;
    }

    public function addLog(TaskLog $taskLog): void
    {
        $this->logs[] = $taskLog->getData();
    }

    public static function createFromDTO(TaskDTO $dto): Task
    {
        $task = new self();
        $task->updateFromDTO($dto);

        return $task;
    }

    public function updateFromDTO(TaskDTO $taskDTO): void
    {
        $this->title = $taskDTO->giveTitle();
        $this->description = $taskDTO->getDescription();
        $this->assignee = $taskDTO->giveAssignee();

        $currentDeadline = $this->hasDeadline() ? $this->getDeadline()->format('Y-m-d') : null;
        $updateDeadline = $taskDTO->hasDeadline() ? $taskDTO->giveDeadline()->format('Y-m-d') : null;
        if ($currentDeadline !== $updateDeadline) {
            $this->deadline = $taskDTO->giveDeadline();
        }
    }

    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getStatusIcon(): string
    {
        $style = Task::STYLES[$this->status] ?? null;

        return $style ? \sprintf('%s text-%s', $style['icon'], $style['text']) : 'fa-dot-circle-o';
    }

    public function getStatusLabel(): string
    {
        return self::STYLES[$this->status]['label'] ?? 'default';
    }

    public function getStatusText(): string
    {
        return self::STYLES[$this->status]['text'] ?? '';
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function hasDeadline(): bool
    {
        return null !== $this->deadline;
    }

    public function getDeadline(): \DateTimeInterface
    {
        if (null === $deadline = $this->deadline) {
            throw new \RuntimeException('No deadline!');
        }

        return $deadline;
    }

    public function getAssignee(): string
    {
        return $this->assignee;
    }

    public function hasDescription(): bool
    {
        return null !== $this->description;
    }

    public function getDescription(): string
    {
        if (null === $description = $this->description) {
            throw new \RuntimeException('No description!');
        }

        return $description;
    }

    public function getLatestCompleted(): ?TaskLog
    {
        if (self::STATUS_COMPLETED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_COMPLETED);
    }

    public function getLatestRejection(): ?TaskLog
    {
        if (self::STATUS_REJECTED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_REJECTED);
    }

    public function getLatestApproved(): ?TaskLog
    {
        if (self::STATUS_APPROVED !== $this->status) {
            return null;
        }

        return $this->getLogLatestByStatus(self::STATUS_APPROVED);
    }

    /**
     * @return TaskLog[]
     */
    public function getLogs(): array
    {
        return \array_map(fn (array $log) => TaskLog::fromData($log), $this->logs);
    }

    public function isOpen(): bool
    {
        return !\in_array($this->status, [Task::STATUS_COMPLETED, Task::STATUS_APPROVED], true);
    }

    public function setAssignee(string $assignee): void
    {
        $this->assignee = $assignee;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    private function getLogLatestByStatus(string $status): ?TaskLog
    {
        $logs = $this->getLogs();
        $statusLogs = \array_filter($logs, fn (TaskLog $log) => $log->getStatus() === $status);
        $latestStatusLog = \array_pop($statusLogs);

        return $latestStatusLog instanceof TaskLog ? $latestStatusLog : null;
    }

    public function getName(): string
    {
        return $this->getId();
    }
}
