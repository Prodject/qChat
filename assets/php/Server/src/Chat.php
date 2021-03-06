<?php

namespace qChat;

use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use Ratchet\RFC6455\Messaging\MessageInterface;
use PDO;

class Chat implements MessageComponentInterface
{
    protected $clients;
    protected $pdo;
    private $ConnectedUsernames;
    private $Users;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->ConnectedUsernames = [];
        $this->pdo = new PDO('sqlite:../database/chats.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store new connection
        $this->clients->attach($conn);
        $this->Users[$conn->resourceId] = $conn;

        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $conn, MessageInterface $msg) {
        $data = json_decode($msg);
        switch ($data->Type) {
            case "Join":
                $this->ConnectedUsernames[$conn->resourceId] = $data->Username;
                echo "Connection {$conn->resourceId} now has an username ({$this->ConnectedUsernames[$conn->resourceId]})!\n";

		$stmt = $this->pdo->query("SELECT * FROM messages;");
		$messages = $stmt->fetchAll();
		print_r($messages);
		foreach ($messages as $message) {
		    $AnswerObject->Message = $message["message"];
                    $AnswerObject->Username = $message["nickname"];
                    $AnswerJson = json_encode($AnswerObject, TRUE);
		    $this->Users[$conn->resourceId]->send($AnswerJson);
		}

                break;
            case "Message":
                if (array_key_exists($conn->resourceId, $this->ConnectedUsernames)) { // User has a username
                    $AnswerObject = new \stdClass();

                    // LOG
                    $NumReceiver = count($this->clients) - 1;
                    echo sprintf('Connection %d sent message "%s" to %d other connection%s' . "\n"
                        , $conn->resourceId, $data->Message, $NumReceiver, $NumReceiver == 1 ? '' : 's'); // better look

                    // The sender is not the receiver -> send to user(s)
                    $AnswerObject->Message = $data->Message;
                    $AnswerObject->Username = $this->ConnectedUsernames[$conn->resourceId];
                    $AnswerJson = json_encode($AnswerObject, TRUE);

       		    $ins = $this->pdo->prepare("INSERT INTO messages (nickname, message) VALUES (:nick, :msg);");
                    $ins->bindParam(':nick', $AnswerObject->Username);
                    $ins->bindParam(':msg', $AnswerObject->Message);
                    $ins->execute();

                    foreach ($this->Users as $User) {
                        $User->send($AnswerJson); // SEND TO ALL
                    }
                }
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Connection was closed, detach user
        $this->clients->detach($conn);
        unset($this->ConnectedUsernames[$conn->resourceId]);

        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";

        $conn->close();
    }
}
