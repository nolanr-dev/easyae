<?php

namespace App\Controller;

use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Repository\FacturationModelRepository;
use App\Repository\AccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Service\DeleteService;

#[Route('/api/client')]
class ClientController extends AbstractController
{
    #[Route(name: 'api_client_index', methods: ["GET"])]
    public function getAll(ClientRepository $clientRepository, SerializerInterface $serializer, TagAwareCacheInterface $cache): JsonResponse
    {
        $idCache = "getAllClients";

        $clientJson = $cache->get($idCache, function (ItemInterface $item) use ($clientRepository, $serializer) {
            $item->tag("client");

            $clientList = $clientRepository->findAll();
            return $serializer->serialize($clientList, 'json', ['groups' => ["client", "clientType"]]);
        });

        return new JsonResponse($clientJson, JsonResponse::HTTP_OK, [], true);
    }

    #[Route(path: '/{id}/carnet-account', name: 'api_client_carnet_account', methods: ["GET"])]
    public function getCarnetAccount(Client $client = null, SerializerInterface $serializer, AccountRepository $accountRepository) {
        $accountsList = $accountRepository->findBy(['client' => $client->getId()]);
        $accountsJson = $serializer->serialize($accountsList, 'json', ['groups' => ["account"]]);
        
        return new JsonResponse($accountsJson, JsonResponse::HTTP_OK, [], true);
    }

    #[Route(path: '/{id}', name: 'api_client_show', methods: ["GET"])]
    public function get(Client $client = null, SerializerInterface $serializer): JsonResponse
    {
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $clientJson = $serializer->serialize($client, 'json', ['groups' => ["client"]]);
        return new JsonResponse($clientJson, JsonResponse::HTTP_OK, [], true);
    }

    #[Route(path: '/{id}/contrats', name: 'api_client_show_Contrats', methods: ["GET"])]
    public function getContrats(Client $client = null, SerializerInterface $serializer): JsonResponse
    {
        if (!$client) {
            return new JsonResponse(['error' => 'Client not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $clientJson = $serializer->serialize($client->getContrats(), 'json', ['groups' => ["client"]]);
        return new JsonResponse($clientJson, JsonResponse::HTTP_OK, [], true);
    }

    #[Route(name: 'api_client_new', methods: ["POST"])]
    public function create(Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $client = $serializer->deserialize($request->getContent(), Client::class, 'json');
        if (!$client) {
            return new JsonResponse(['error' => 'Invalid data'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (is_null($client->getStatus())) {
            $client->setStatus("on");
        }

        $contact = $client->getContact();
        if ($contact) {
            $entityManager->persist($contact);
        }

        $entityManager->persist($client);
        $entityManager->flush();

        $cache->invalidateTags(["client"]);

        $clientJson = $serializer->serialize($client, 'json', ['groups' => "client"]);
        return new JsonResponse($clientJson, JsonResponse::HTTP_CREATED, [], true);
    }

    #[Route(path: "/{id}", name: 'api_client_edit', methods: ["PATCH"])]
    public function update(Client $client, UrlGeneratorInterface $urlGenerator, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $updatedClient = $serializer->deserialize($request->getContent(), Client::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $client]);
        $updatedClient->setStatus("on");

        $entityManager->persist($updatedClient);
        $entityManager->flush();

        $cache->invalidateTags(["client"]);

        $location = $urlGenerator->generate("api_client_show", ['id' => $updatedClient->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT, ["Location" => $location]);
    }



    #[Route(path: "/update_facturation_model/{id}", name: 'update_client_facturationModel', methods: ["POST"])]
    public function updateFacturationModel(Client $client,FacturationModelRepository $modelRepository, UrlGeneratorInterface $urlGenerator, Request $request, SerializerInterface $serializer, EntityManagerInterface $entityManager, TagAwareCacheInterface $cache): JsonResponse
    {
        $data = $request->toArray();

        if (isset($data["facturationModel"])) {
            $model = $modelRepository->find($data["facturationModel"]);
        }

        $updatedClient = $serializer->deserialize($request->getContent(), Client::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $client]);
        $updatedClient
            ->setStatus("on")
            ->setFacturationModel($model)
        ;

        $entityManager->persist($updatedClient);
        $entityManager->flush();

        $cache->invalidateTags(["client"]);

        $location = $urlGenerator->generate("api_client_show", ['id' => $updatedClient->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT, ["Location" => $location]);
    }
    #[Route(path: "/{id}", name: 'api_client_delete', methods: ["DELETE"])]

    public function delete(Client $client, Request $request, DeleteService $deleteService): JsonResponse
    {
        $data = $request->toArray();

        return $deleteService->deleteEntity($client, $data, 'client');
    }
}
