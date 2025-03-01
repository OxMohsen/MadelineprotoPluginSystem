<?php

declare(strict_types=1);

namespace OxMohsen\Plugins\AdminPlugins;

use OxMohsen\Plugins\AdminPlugin;

final class DeletemessagesPlugin extends AdminPlugin
{
    /**
     * The name and signature of the plugin.
     *
     * @var string
     */
    protected $name = 'deletemessages';

    /**
     * The plugin description.
     *
     * @var string
     */
    protected $description = 'delete some messages of supergroups or channel';

    /**
     * The plugin regex pattern.
     *
     * @var string
     */
    protected $pattern = '/^[\!\#\.\/]delmsgs ([\d]+)$/i';

    /**
     * The plugin usage.
     * This will help the user to find out how to use this plugin.
     *
     * @var string
     */
    protected $usage = '!delmsgs 100';

    public function execute(): \Generator
    {
        $message      = 'i can\'t delete message here.';
        $canDelete    = false;
        $isSupergroup = yield $this->isChannelOrSupergroup();
        if (true === $isSupergroup) {
            $canDelete = yield $this->canDeleteMessage();
        }

        if ($canDelete && $isSupergroup) {
            yield $this->MadelineProto->messages->sendMessage([
                'peer'    => $this->MadelineProto->update->getUpdate()->toArray(),
                'message' => 'Deleting messages ...',
            ]);
            $countOfDeletedMessages = (int) yield $this->deleteMessages();

            $message = "{$countOfDeletedMessages} messages successfully deleted.";
        }

        yield $this->MadelineProto->messages->sendMessage([
            'peer'            => $this->MadelineProto->update->getUpdate()->toArray(),
            'message'         => $message,
            'reply_to_msg_id' => $this->MadelineProto->update->getMessageId(),
        ]);
    }

    /**
     * Checks if bot can delete message.
     *
     * @return \Generator `true` if bot can delete message, `false` otherwise
     */
    private function canDeleteMessage(): \Generator
    {
        $channelParticipant = yield $this->MadelineProto->channels->getParticipant([
            'channel'     => $this->MadelineProto->update->getUpdate()->toArray(),
            'participant' => 'me',
        ]);

        $type      = $channelParticipant['participant']['_']                               ?? null;
        $canDelete = $channelParticipant['participant']['admin_rights']['delete_messages'] ?? false;

        return ('channelParticipantAdmin' === $type && true === $canDelete) || 'channelParticipantCreator' === $type;
    }

    /**
     * Delete messages.
     *
     * @return \Generator count of deleted message
     */
    private function deleteMessages(): \Generator
    {
        $inputNumber          = (int) $this->getMatches()[1];
        $lastMessageForDelete = $this->MadelineProto->update->getMessageId();

        if (0 === $lastMessageForDelete) {
            return 0;
        }

        $firstMessageForDelete = $lastMessageForDelete - $inputNumber > 0 ? $lastMessageForDelete - $inputNumber : 1;
        $allMessageForDelete   = range($firstMessageForDelete, $lastMessageForDelete);

        foreach (array_chunk($allMessageForDelete, 40) as $ids) {
            yield $this->MadelineProto->channels->deleteMessages([
                'channel' => $this->MadelineProto->update->getUpdate()->toArray(),
                'id'      => $ids,
            ]);

            yield $this->MadelineProto->sleep(mt_rand(1, 3));
        }

        return $inputNumber;
    }

    /**
     * Checks if current chat is `supergroup` or `channel`.
     *
     * @return \Generator `true` if the current chat is `supergroup` or `channel`, `false` otherwise
     */
    private function isChannelOrSupergroup(): \Generator
    {
        $type = yield $this->MadelineProto->getInfo($this->MadelineProto->update->getUpdate()->toArray())['type'];

        return 'supergroup' === $type || 'channel' === $type;
    }
}
