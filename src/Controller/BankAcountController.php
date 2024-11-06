<?php

namespace App\Controller;

use App\Exception\InsufficientFundsException;
use App\Exception\WithdrawalFailedException;
use App\Services\BankAccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BankAcountController extends AbstractController
{
    #[Route('/bank/withdraw', name: 'app_bank_acount', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('bank_acount/index.html.twig');
    }

    #[Route('/bank/withdraw', name: 'app_bank_acount_process', methods: ['POST'])]
    public function withdraw(Request $request, BankAccountService $bankAccountService): JsonResponse
    {
        $accountId = $request->get('accountId');
        $amount = $request->get('amount');

        try{
            $bankAccountService->withdraw($accountId, $amount);
            return new JsonResponse(['message' => 'Withdrawal successful'], Response::HTTP_OK);
            }catch (InsufficientFundsException $e){
                return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
            }catch(WithdrawalFailedException $e){
                return new JsonResponse(['message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
