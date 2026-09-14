<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Form\ContactType;
use App\Form\EditContactType;
use App\Repository\ContactRepository;
use App\Repository\UserRepository;
use Cocur\Slugify\Slugify;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/admin/contact')]
class ContactController extends AbstractController
{
    private $contactRepository;
    private $userRepository;

    public function __construct(ContactRepository $contactRepository, UserRepository $userRepository)
    {
        $this->contactRepository = $contactRepository;
        $this->userRepository = $userRepository;
    }

    /*
    * Retoune les contacts
    */ 

    #[Route('/', name: 'app_contacts')]
    public function showContacts(): Response
    {

        $contacts = $this->contactRepository->findAll();
        
        return $this->render('admin/contact_list.html.twig', [
            'contacts' => $contacts,
        ]);
    }


    #[Route('/{id}/{slug}', name: 'app_contact')]
    public function showContact(Contact $contact): Response
    {
        // 404 automatique si l'identifiant n'existe pas : le test if(!$contact)
        // etait place apres $contact->getUser(), donc inatteignable
        $user = $contact->getUser();
        $customer = $user->getCustomer();

        return $this->render('admin/contact_show.html.twig', [
            'contact' => $contact,
            'user' => $user,
            'customer' => $customer
        ]);

    }
   
    // Create a contact from USER avec user.id + user.slug

    #[Route('/creer-un-contact/{id}/{slug}', name: 'app_contact_add')]
    public function createContact(Request $request, $id): Response
    {
        $user = $this->userRepository->findOneById($id);
        $slug = $user->getSlug();
        
        // récup infos customer pour redirect si exist
        if($user->getCustomer()){

            $customer = $user->getCustomer();
            $customerId = $customer->getId();
            $customerSlug = $customer->getSlug();
            
        }

        $contact = new Contact();
        
        $form = $this->createForm(ContactType::class, $contact, [
            'user' => $user,
        ]);
        $contact->setUser($user);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted()){
            
            if ($form->isValid()) {
                
                $contact = $form->getData();
                
                $fullname = $contact->getFirstname()." ".$contact->getLastname();
                $slugify = new Slugify();
                $slugify = $slugify->slugify($fullname);
                $contact->setSlug($slugify);

                $this->contactRepository->save($contact, true);

                
                $this->addFlash(
                    'success',
                    'La création du contact est bien enregistrée.'
                );
                
                if ($user->getCustomer()) {
                    // Return on Customer View if $customer is NOT Null
                    return $this->redirectToRoute('app_customer', ['id' => $customerId, 'slug' => $customerSlug]);
                } else {
                    // Return on User View if $customer is Null
                    return $this->redirectToRoute('app_user_show', ['id' => $id, 'slug' => $slug]);
                }
           } 
        }
        return $this->render('admin/contact_new.html.twig', array(
            'user' => $user,
            'flash' => $this,
            'form' => $form->createView(), 
        ));
    }
    
    
    // Edit 

    #[Route('/modifier-un-contact/{id}/{slug}', name: 'app_contact_edit')]
    public function editContact(Request $request, Contact $contact): Response
    {
        // 404 automatique si l'identifiant n'existe pas
        $user = $contact->getUser();
        $customer = $user->getCustomer();

        $form = $this->createForm(EditContactType::class, $contact);

        $form->handleRequest($request);
        

        if ($form->isSubmitted() && $form->isValid()) {
            $this->contactRepository->save($contact, true);
            $this->addFlash(
                'success',
                'La modification du contact est bien enregistrée.'
            );
            return $this->redirectToRoute('app_contact', ['id' => $contact->getId(), 'slug' => $contact->getSlug()]);
        }

        return $this->render('admin/contact_edit.html.twig', [
            'form' => $form->createView(),
            'flash' => $this,
            'user' => $user,
            'customer' => $customer
        ]);
    }

    // POST only : a destructive action must never be reachable by following a link.
    // The CSRF token travels in the body, not in the query string where it would
    // end up in the server logs, the browser history and the Referer header.
    #[Route('/{id}/{slug}/supprimer', name: 'app_contact_delete', methods: ['POST'])]
    public function deleteContact(Request $request, Contact $contact): Response
    {
        $user = $contact->getUser();
        $userId = $user->getId();
        $slug = $user->getSlug();

        if (!$this->isCsrfTokenValid('delete_contact' . $contact->getId(), $request->request->get('_token'))) {
            $this->addFlash(
                'error',
                'Vous ne pouvez pas supprimer cet élément.'
            );

            return $this->redirectToRoute('app_user_show', ['id' => $userId, 'slug' => $slug]);
        }

        $customer = $user->getCustomer();

        $this->contactRepository->remove($contact, true);

        $this->addFlash(
            'success',
            'Le contact a été supprimé.'
        );

        // back to the customer page when the contact belongs to one, to the user page otherwise
        if ($customer) {
            return $this->redirectToRoute('app_customer', [
                'id' => $customer->getId(),
                'slug' => $customer->getSlug(),
            ]);
        }

        return $this->redirectToRoute('app_user_show', ['id' => $userId, 'slug' => $slug]);
    }
            
}
