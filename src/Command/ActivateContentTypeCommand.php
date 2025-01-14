<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Command;

use EMS\CommonBundle\Helper\EmsFields;
use EMS\CoreBundle\Service\ContentTypeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class ActivateContentTypeCommand extends Command
{
    protected static $defaultName = 'ems:contenttype:activate';

    /** @var ContentTypeService */
    protected $contentTypeService;
    /** @var SymfonyStyle */
    private $io;
    /** @var LoggerInterface */
    private $logger;
    /** @var bool */
    private $deactivate;

    public const ARGUMENT_CONTENTTYPES = 'contenttypes';
    public const OPTION_ALL = 'all';
    public const DEACTIVATE = 'deactivate';
    public const FORCE = 'force';

    public function __construct(LoggerInterface $logger, ContentTypeService $contentTypeService)
    {
        $this->contentTypeService = $contentTypeService;
        $this->logger = $logger;
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $fileNames = \implode(', ', $this->contentTypeService->getAllNames());
        $this
            ->addArgument(
                self::ARGUMENT_CONTENTTYPES,
                InputArgument::IS_ARRAY,
                \sprintf('Optional array of contenttypes to create. Allowed values: [%s]', $fileNames)
            )
            ->addOption(
                self::OPTION_ALL,
                null,
                InputOption::VALUE_NONE,
                \sprintf('Make all contenttypes: [%s]', $fileNames)
            )
            ->addOption(
                self::DEACTIVATE,
                null,
                InputOption::VALUE_NONE,
                \sprintf('Deactivate contenttypes')
            )
            ->addOption(
                self::FORCE,
                null,
                InputOption::VALUE_NONE,
                \sprintf('Activate the contenttypes even if the mapping is not up to date (flagged as draft)')
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string[] $types */
        $types = $input->getArgument(self::ARGUMENT_CONTENTTYPES);
        $force = $input->getOption(self::FORCE);

        foreach ($types as $type) {
            try {
                $contentType = $this->contentTypeService->getByName($type);
                if (false === $contentType) {
                    throw new \RuntimeException('Content Type not found');
                }
                if ($contentType->getDirty() && !$this->deactivate && !$force) {
                    $this->io->error(\sprintf('Content type %s is dirty please update it\'s mapping or use the force flag', $contentType->getName()));
                    continue;
                }
                $contentType->setActive(!$this->deactivate);
                $this->contentTypeService->persist($contentType);
                $this->logger->notice($this->deactivate ? 'command.contenttype.deactivate' : 'command.contenttype.activate', [
                    EmsFields::LOG_OPERATION_FIELD => EmsFields::LOG_OPERATION_UPDATE,
                    EmsFields::LOG_CONTENTTYPE_FIELD => $contentType->getName(),
                ]);
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
            }
        }

        return 0;
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $this->deactivate = true === $input->getOption(self::DEACTIVATE);
        $this->io->title($this->deactivate ? 'Deactivate contenttypes' : 'Activate contenttypes');
        $this->io->section('Checking input');

        $types = $input->getArgument(self::ARGUMENT_CONTENTTYPES);
        if (null === $types || \is_string($types)) {
            throw new \RuntimeException('Unexpected content type names');
        }

        if (!$input->getOption(self::OPTION_ALL) && 0 == \count($types)) {
            $this->chooseTypes($input, $output);
        }

        if ($input->getOption(self::OPTION_ALL)) {
            $this->optionAll($input);
        }
    }

    private function chooseTypes(InputInterface $input, OutputInterface $output): void
    {
        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion(
            $this->deactivate ? 'Select the contenttypes you want to deactivate' : 'Select the contenttypes you want to activate',
            \array_merge([self::OPTION_ALL], $this->contentTypeService->getAllNames())
        );
        $question->setMultiselect(true);

        $types = $helper->ask($input, $output, $question);
        if (\in_array(self::OPTION_ALL, $types)) {
            $input->setOption(self::OPTION_ALL, true);
            $this->io->note(\sprintf('Continuing with option --%s', self::OPTION_ALL));
        } else {
            $input->setArgument(self::ARGUMENT_CONTENTTYPES, $types);
            $this->io->note(['Continuing with contenttypes:', \implode(', ', $types)]);
        }
    }

    private function optionAll(InputInterface $input): void
    {
        $types = $this->contentTypeService->getAllNames();
        $input->setArgument(self::ARGUMENT_CONTENTTYPES, $types);
        $this->io->note(['Continuing with contenttypes:', \implode(', ', $types)]);
    }
}
