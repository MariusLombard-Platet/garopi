<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Article;
use AppBundle\Form\Type\CommentType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Comment;

/**
 * Class ArticleController
 */
class ArticleController extends Controller
{
    /**
     * @Route("/articles/{slug}")
     * @Template("AppBundle:Article:show.html.twig")
     * @ParamConverter("article", options={"mapping": {"slug": "slug"}, "entity_manager" = "default"})
     */
    public function showAction(Article $article, Request $request)
    {
        // Comment to highlight
        $highlight = null;
        $comment = new Comment();

        $form = $this->createForm(new CommentType(), $comment);
        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $article->addComment($comment);
            $comment->setAuthor($this->getUser());
            $em->persist($comment);
            $em->flush();

            // Remove field after persist
            $form = $this->createForm(new CommentType(), new Comment());
            $highlight = $comment;
        }

        return array(
            'article' => $article,
            'form' => $form->createView(),
            'highlight' => $highlight,
        );
    }
}
