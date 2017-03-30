<?php
/**
 * This file is part of prooph/proophessor-do.
 * (c) 2014-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\ProophessorDo\Model\Todo\Handler;

use Prooph\ProophessorDo\Model\Todo\Command\NotifyUserOfExpiredTodo;
use Prooph\ProophessorDo\Model\Todo\Query\GetTodoById;
use Prooph\ProophessorDo\Model\User\Query\GetUserById;
use Prooph\ServiceBus\QueryBus;
use Zend\Mail\Message;
use Zend\Mail\Transport\TransportInterface;

class NotifyUserOfExpiredTodoHandler
{
    /**
     * @var QueryBus
     */
    private $queryBus;

    /**
     * @var TransportInterface
     */
    private $mailer;

    public function __construct(
        QueryBus $queryBus,
        TransportInterface $mailer
    ) {
        $this->queryBus = $queryBus;
        $this->mailer = $mailer;
    }

    public function __invoke(NotifyUserOfExpiredTodo $command): void
    {
        $todo = null;
        $this->queryBus->dispatch(new GetTodoById($command->todoId()->toString()))
            ->then(
                function ($result) use (&$todo) {
                    $todo = $result;
                }
            );
        $user = null;
        $this->queryBus->dispatch(new GetUserById($todo->assignee_id))
            ->then(
                function ($result) use (&$user) {
                    $user = $result;
                }
            );

        $message = sprintf(
            'Hi %s! Just a heads up: your todo `%s` has expired on %s.',
            $user->name,
            $todo->text,
            $todo->deadline
        );

        $mail = new Message();
        $mail->setBody($message);
        $mail->setEncoding('utf-8');
        $mail->setFrom('reminder@localhost', 'Proophessor-do');
        $mail->addTo($user->email, $user->name);
        $mail->setSubject('Proophessor-do Todo expired');

        $this->mailer->send($mail);
    }
}
