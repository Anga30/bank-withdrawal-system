<?php 

namespace App\Services;

use App\Entity\Account;
use App\Entity\WithdrawalEvent;
use App\Exception\InsufficientFundsException;
use App\Exception\WithdrawalFailedException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Serializer\SerializerInterface;
use Aws\Sns\SnsClient;
use Psr\Log\LoggerInterface;

class BankAccountService
{
    private string $snsTopicArn;
    public function __construct(private EntityManagerInterface $em, private SerializerInterface $serializer,
    private SnsClient $snsClient, private LoggerInterface $logger)
    {}
    public function withdraw($accountId, $amount)
    {
        $this->em->beginTransaction();
        try{
            $account = $this->em->getRepository(Account::class)->find($accountId);
            $balance = $account->getBalance();
            if($account){
                if($balance != null && $balance >= $amount){
                    //update
                    try{
                        $account->setBalance($balance - $amount);
                        $this->em->persist($account);
                        $this->em->flush();

                        return "Withdrawal Succuessful";
                    }catch(Exception $e){
                        throw new WithdrawalFailedException();
                    }
                }else{
                    throw new InsufficientFundsException();
                }
            }
            //publish SNS event
            $event = new WithdrawalEvent($amount,  $accountId, 'SUCCESSFUL');
            $this->publishWithdrawalEven($event);
            $this->em->commit();

        }catch(Exception $e){
            $this->em->rollback();
            throw new WithdrawalFailedException('Withdrawal failed due to an error: '.$e);
        }
    }

    private function publishWithdrawalEven(WithdrawalEvent $event)
    {
        try{
            $eventJson = $this->serializer->serialize($event, 'json');
            $this->snsClient->publish([
                'Message' => $eventJson,
                'TopicArn' => $this->snsTopicArn
            ]);
            $this->logger->info('Withdrawal event published for account: '.$event->getAccountId());
        }catch(Exception $e){
            $this->logger->info('Failed to publish withdrawal event: '. $e->getMessage());
        }
    }
}