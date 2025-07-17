<?php

namespace App\Controller;

use App\Entity\Observer;
use App\Form\ObserverType;
use App\Repository\ObserverRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/observers')]
#[IsGranted('ROLE_ADMIN')]
class ObserverController extends AbstractController
{
    #[Route('/', name: 'observer_index', methods: ['GET'])]
    public function index(ObserverRepository $observerRepository): Response
    {
        return $this->render('observer/index.html.twig', [
            'observers' => $observerRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'observer_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $observer = new Observer();
        $form = $this->createForm(ObserverType::class, $observer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($observer);
            $entityManager->flush();

            $this->addFlash('success', 'Observer created successfully');
            return $this->redirectToRoute('observer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observer/new.html.twig', [
            'observer' => $observer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'observer_show', methods: ['GET'])]
    public function show(Observer $observer): Response
    {
        return $this->render('observer/show.html.twig', [
            'observer' => $observer,
        ]);
    }

    #[Route('/{id}/edit', name: 'observer_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Observer $observer, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ObserverType::class, $observer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Observer updated successfully');
            return $this->redirectToRoute('observer_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observer/edit.html.twig', [
            'observer' => $observer,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/regenerate-token', name: 'observer_regenerate_token', methods: ['POST'])]
    public function regenerateToken(Request $request, Observer $observer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('regenerate'.$observer->getId(), $request->request->get('_token'))) {
            $observer->regenerateAccessToken();
            $entityManager->flush();
            
            $this->addFlash('success', 'Access token regenerated successfully');
        }

        return $this->redirectToRoute('observer_show', ['id' => $observer->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'observer_delete', methods: ['POST'])]
    public function delete(Request $request, Observer $observer, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$observer->getId(), $request->request->get('_token'))) {
            $entityManager->remove($observer);
            $entityManager->flush();
            
            $this->addFlash('success', 'Observer deleted successfully');
        }

        return $this->redirectToRoute('observer_index', [], Response::HTTP_SEE_OTHER);
    }
} 