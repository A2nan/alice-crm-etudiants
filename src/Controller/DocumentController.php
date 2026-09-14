<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Document;
use App\Form\DocumentType;
use App\Repository\DocumentRepository;
use App\Security\Voter\DocumentVoter;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;


#[Route('/document')]
class DocumentController extends AbstractController
{
    private $entityManager;
    private $documentRepository;

    public function __construct(EntityManagerInterface $entityManager, DocumentRepository $documentRepository)
    {
        $this->entityManager = $entityManager;
        $this->documentRepository = $documentRepository;
    }
    
    #[Route('/', name: 'app_document_list', methods: ['GET'])]
    public function index(PaginatorInterface $paginator, Request $request): Response
    {
        $user = $this->getUser();
        
        if ($this->isGranted('ROLE_ADMIN')) {
            // If the user is an admin, display all documents
            $query = $this->entityManager->getRepository(Document::class)
                ->createQueryBuilder('d')
                ->leftJoin('d.user', 'u');

        } else {
            // Otherwise, display only the documents of the logged-in user
            $query = $this->entityManager
                ->getRepository(Document::class)
                ->createQueryBuilder('d')
                ->join('d.user', 'u')
                ->where('u.id = :user_id')
                ->setParameter('user_id', $user->getId())
                ->orderBy('d.date', 'DESC');
        }
    
        // 
        $pagination = $paginator->paginate(
            $query, /* query builder containing the data to paginate */
            $request->query->getInt('page', 1), /* default page number */
            10, /* number of elements per page */
            ['defaultSortFieldName' => 'd.date', 'defaultSortDirection' => 'desc']
        );
        
        return $this->render('document/list.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/nouveau', name: 'app_document_new', methods: ['GET', 'POST'])]
    public function new(Request $request, SluggerInterface $slugger, EntityManagerInterface $entityManager): Response
    {
        $document = new Document();
        
        if ($this->isGranted('ROLE_ADMIN')) {
            $adminUser = [$this->getUser()];
        } else {
            // entityManager is used to create an instance of QueryBuilder
            $adminUser = $entityManager->createQueryBuilder()
                ->select('u')
                ->from(User::class, 'u')
                ->where('u.roles LIKE :role')
                ->setParameter('role', '%ROLE_ADMIN%')
                ->getQuery()
                ->getResult();
        }
        
        $users = array_merge([$this->getUser()], $adminUser);
        
        foreach ($users as $user) {
            $document->addUser($user);
        }

        $document->setDate(new \DateTime());
        $form = $this->createForm(DocumentType::class, $document);

        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            
            $file = $form->get('fileName')->getData();
            
            if ($file) {
                //needed to get the original file name of the downloaded file without the extension
                $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $safeFilename = substr($safeFilename, 0, 20); // extracts the first 20 characters
                $currentDate = date('Ymd'); // add date
                // random_bytes() is cryptographically secure, unlike uniqid() which is derived from the clock
                $newFilename = $currentDate.'-'.$safeFilename.'-'.bin2hex(random_bytes(16)).'.'.$file->guessExtension();
                try {
                    // Move the file to the directory where brochures are stored
                    $file->move(
                        $this->getParameter('documents_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    throw $e; // Propagate the exception to the higher level
                }
                // updates the 'file' property to store the file name
                $document->setFileName($newFilename);
            }
            $this->documentRepository->save($document, true);
            $this->addFlash(
               'success',
               'Le document est bien enregistré.'
            );
            return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('document/new.html.twig', [
            'document' => $document,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_document_show', methods: ['GET'])]
    public function show(Document $document, AuthorizationCheckerInterface $authChecker): Response
    {
        // ADMIN sees everything, a user only sees the documents linked to him
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        // isAuthorized still drives the display of the edit/delete actions in the template
        $isAuthorized = $authChecker->isGranted('ROLE_ADMIN');

        return $this->render('document/show.html.twig', [
            'document' => $document,
            'isAuthorized' => $isAuthorized,
            'authorizedUsers' => $document->getUser()->toArray(),
        ]);
    }

    /**
     * Serves the uploaded file. Stored outside the web root, so this is the only
     * way to reach it : the access rights are checked on every request.
     */
    #[Route('/{id}/telecharger', name: 'app_document_download', methods: ['GET'], defaults: ['disposition' => 'attachment'])]
    #[Route('/{id}/apercu', name: 'app_document_preview', methods: ['GET'], defaults: ['disposition' => 'inline'])]
    public function download(Document $document, string $disposition): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(DocumentVoter::VIEW, $document);

        $path = $this->getParameter('documents_directory').'/'.$document->getFileName();

        if (!$document->getFileName() || !is_file($path)) {
            throw $this->createNotFoundException('Le fichier est introuvable.');
        }

        // the name sent to the browser is the business name, not the name on disk
        $downloadName = $document->getName().'.'.pathinfo($document->getFileName(), PATHINFO_EXTENSION);

        return $this->file($path, $downloadName, $disposition);
    }

    #[Route('/{id}/modifier', name: 'app_document_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Document $document, AuthorizationCheckerInterface $authChecker): Response
    {
        // only ADMIN is authorized to edit document
        
        $isAuthorized = $authChecker->isGranted('ROLE_ADMIN');

        if ($this->isGranted('ROLE_ADMIN')) {

            $form = $this->createForm(DocumentType::class, $document, [
                'disable_file_upload' => true, //option to disable the file field
            ]);
            $form->handleRequest($request);
            
            if ($form->isSubmitted() && $form->isValid()) {
                        
                $this->documentRepository->save($document, true);
                return $this->redirectToRoute('app_document_show', ['id' => $document->getId()], Response::HTTP_SEE_OTHER);
            }

        }
        

        return $this->render('document/edit.html.twig', [
            'document' => $document,
            // if $isAuthorized == true, we return $form->createView() with value 'form'. Else $isAuthorized == false, it returns null and no form created
            'form' => $isAuthorized ? $form->createView() : null,
            'isAuthorized' => $isAuthorized,
        ]);
    }

    #[Route('/{id}', name: 'app_document_delete', methods: ['POST'])]
    public function delete(Request $request, Document $document): Response
    {
        if ($this->isCsrfTokenValid('delete'.$document->getId(), $request->request->get('_token'))) {
            $this->documentRepository->remove($document, true);
        }

        return $this->redirectToRoute('app_document_list', [], Response::HTTP_SEE_OTHER);
    }
}
