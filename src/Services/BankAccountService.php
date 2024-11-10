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
    public function __construct(private EntityManagerInterface $em, private SerializerInterface $serializer,
    private SnsClient $snsClient, private LoggerInterface $logger, private string $snsTopicArn)
    {}
    public function withdraw(int $accountId, int $amount)
    {
        $this->em->beginTransaction();
        try{
            $account = $this->em->getRepository(Account::class)->findOneBy(['accountId' => $accountId]);
            
            if($account){
                $balance = $account->getBalance();
                if($balance != null && $balance >= $amount){
                    //update
                    try{
                        $account->setBalance($balance - $amount);
                        $this->em->flush();

                        //publish SNS event
                        $event = new WithdrawalEvent($amount,  $accountId, 'SUCCESSFUL');
                        $this->publishWithdrawalEven($event);
                        $this->em->commit();

                        return "Withdrawal Succuessful";
                    }catch(Exception $e){
                        throw new WithdrawalFailedException();
                    }
                }else{
                    throw new InsufficientFundsException();
                }
            }
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