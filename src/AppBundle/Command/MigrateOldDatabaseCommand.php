<?php

namespace AppBundle\Command;

use AppBundle\Entity\Article;
use AppBundle\Entity\ArticleCategory;
use AppBundle\Entity\ArticleMedia;
use AppBundle\Entity\Comment;
use AppBundle\Entity\Quote;
use Garopi\LegacyWrapperBundle\Entity\AdminQuotations;
use Garopi\LegacyWrapperBundle\Entity\Articles;
use Garopi\LegacyWrapperBundle\Entity\Comments;
use Sonata\MediaBundle\Tests\Entity\Media;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Class MigrateOldDatabaseCommand
 */
class MigrateOldDatabaseCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('garopi:import:old-database')
            ->setDescription('Importr la base de données depuis l\'ancien site.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->loadArticlesAndCategoriesFromOldDatabase();
        $this->loadCommentsFromOldDatabase();
        $this->loadQuotesFromOldDatabase();
    }

    protected function loadArticlesAndCategoriesFromOldDatabase()
    {
        $em = $this->getContainer()->get('doctrine')->getManager('default');
        $emLegacy = $this->getContainer()->get('doctrine')->getManager('legacy');

        $categories = array();
        $oldCategories = $emLegacy->getRepository('GaropiLegacyWrapperBundle:AdminCategories')->findAll();
        foreach ($oldCategories as $oldCategory) {
            $id = $oldCategory->getId();
            $category = $em->getRepository('AppBundle:ArticleCategory')->findOneBy(array('name' => $oldCategory->getName()));
            $categories[$id] = $category;
        }

        $oldArticles = $emLegacy->getRepository('GaropiLegacyWrapperBundle:Articles')->findAll();

        $mediaManager = $this->getContainer()->get('sonata.media.manager.media');

        /** @var Articles $oldArticle */
        foreach ($oldArticles as $oldArticle) {
            $legacyId = $oldArticle->getId();
            $title = $oldArticle->getTitle();
            $summary = $oldArticle->getSummary();
            $content = $this->bbcode_to_html($oldArticle->getContent());
            $authorName = $oldArticle->getAuthor();
            $createdAt = new \DateTime($oldArticle->getCreatedAt());
            $updatedAt = new \DateTime($oldArticle->getUpdatedAt());

            /** @var ArticleCategory $category */
            $category = $categories[$oldArticle->getCategoryId()];

            $article = new Article();
            if ($oldArticle->getPictureFileName()) {
                $finder = new Finder();
                $path = $this->getContainer()->get('kernel')->getRootDir() . '/../legacy/photos/000/000/' . sprintf("%'.03d", $oldArticle->getId()) . '/original/';
                $finder->files()->in($path);

                /** @var File $file */
                foreach ($finder as $file) {
                    try {
                        /** @var Media $media */
                        $media = $mediaManager->create();
                        $media->setBinaryContent($file->getRealPath());
                        $media->setDescription($summary);
                        $media->setEnabled(true);
                        $media->setContext('default');
                        $media->setProviderName('sonata.media.provider.image');
                        $em->persist($media);

                        $articleMedia = new ArticleMedia();
                        $articleMedia->setArticle($article);
                        $articleMedia->setMedia($media);
                        $em->persist($articleMedia);
                    } catch(\Exception $e) {
                        var_dump($e);
                    }
                }
            }

            $article->setTitle($title);
            $article->setSummary($summary);
            $article->setContent($content);
            $article->setAuthorName($authorName);
            $article->setPublished(true);
            $article->setCreatedAt($createdAt);
            $article->setUpdatedAt($updatedAt);
            $article->setLegacyId($legacyId);
            $article->setCategory($category);
            $category->addArticle($article);

            $em->persist($article);
            $em->flush();
        }
    }

    protected function loadCommentsFromOldDatabase()
    {
        $em = $this->getContainer()->get('doctrine')->getManager('default');
        $emLegacy = $this->getContainer()->get('doctrine')->getManager('legacy');
        $userManager = $this->getContainer()->get('app.service.user');

        $oldComments = $emLegacy->getRepository('GaropiLegacyWrapperBundle:Comments')->findAll();

        /** @var Comments $oldComment */
        foreach ($oldComments as $oldComment) {

            $authorLogin = $oldComment->getAuthor();
            $content = $oldComment->getContent();
            $articleId = $oldComment->getArticleId();
            $createdAt = new \DateTime($oldComment->getCreatedAt());
            $updatedAt = new \DateTime($oldComment->getUpdatedAt());

            $comment = new Comment();

            $article = $em->getRepository('AppBundle:Article')->findOneBy(array('legacyId' => $articleId));
            if (!$article) {
                continue;
            }

            $author = $userManager->createOrUpdateUserByLogin($authorLogin, true);
            if (!$author) {
                continue;
            }
            // Hack to update users that was loaded with data fixtures
            if (!$author->getPhoto()) {
                $userManager->createOrUpdateUserByLogin($authorLogin, false);
            }

            $article->addComment($comment);
            $comment->setAuthor($author);
            $comment->setContent($content);
            $comment->setCreatedAt($createdAt);
            $comment->setUpdatedAt($updatedAt);

            $em->persist($comment);
            $em->flush();
        }
    }

    protected function loadQuotesFromOldDatabase()
    {
        $em = $this->getContainer()->get('doctrine')->getManager('default');
        $emLegacy = $this->getContainer()->get('doctrine')->getManager('legacy');

        $oldQuotes = $emLegacy->getRepository('GaropiLegacyWrapperBundle:AdminQuotations')->findAll();

        /** @var AdminQuotations $oldQuote */
        foreach ($oldQuotes as $oldQuote) {

            $content = $oldQuote->getContent();
            $authorName = $oldQuote->getAuthor();
            $createdAt = new \DateTime($oldQuote->getCreatedAt());
            $updatedAt = new \DateTime($oldQuote->getUpdatedAt());

            $quote = new Quote();

            $quote->setAuthorName($authorName);
            $quote->setContent($content);
            $quote->setCreatedAt($createdAt);
            $quote->setUpdatedAt($updatedAt);

            $em->persist($quote);
            $em->flush();
        }
    }

    protected function bbcode_to_html($bbtext){
        $bbtags = array(
            '[heading1]' => '<h1>','[/heading1]' => '</h1>',
            '[heading2]' => '<h2>','[/heading2]' => '</h2>',
            '[heading3]' => '<h3>','[/heading3]' => '</h3>',
            '[h1]' => '<h1>','[/h1]' => '</h1>',
            '[h2]' => '<h2>','[/h2]' => '</h2>',
            '[h3]' => '<h3>','[/h3]' => '</h3>',

            '[paragraph]' => '<p>','[/paragraph]' => '</p>',
            '[para]' => '<p>','[/para]' => '</p>',
            '[p]' => '<p>','[/p]' => '</p>',
            '[left]' => '<p style="text-align:left;">','[/left]' => '</p>',
            '[right]' => '<p style="text-align:right;">','[/right]' => '</p>',
            '[center]' => '<p style="text-align:center;">','[/center]' => '</p>',
            '[justify]' => '<p style="text-align:justify;">','[/justify]' => '</p>',

            '[bold]' => '<span style="font-weight:bold;">','[/bold]' => '</span>',
            '[italic]' => '<span style="font-weight:bold;">','[/italic]' => '</span>',
            '[underline]' => '<span style="text-decoration:underline;">','[/underline]' => '</span>',
            '[b]' => '<span style="font-weight:bold;">','[/b]' => '</span>',
            '[i]' => '<span style="font-weight:bold;">','[/i]' => '</span>',
            '[u]' => '<span style="text-decoration:underline;">','[/u]' => '</span>',
            '[break]' => '<br>',
            '[br]' => '<br>',
            '[newline]' => '<br>',
            '[nl]' => '<br>',

            '[unordered_list]' => '<ul>','[/unordered_list]' => '</ul>',
            '[list]' => '<ul>','[/list]' => '</ul>',
            '[ul]' => '<ul>','[/ul]' => '</ul>',

            '[ordered_list]' => '<ol>','[/ordered_list]' => '</ol>',
            '[ol]' => '<ol>','[/ol]' => '</ol>',
            '[list_item]' => '<li>','[/list_item]' => '</li>',
            '[li]' => '<li>','[/li]' => '</li>',

            '[*]' => '<li>','[/*]' => '</li>',
            '[code]' => '<code>','[/code]' => '</code>',
            '[preformatted]' => '<pre>','[/preformatted]' => '</pre>',
            '[pre]' => '<pre>','[/pre]' => '</pre>',
        );

        $bbtext = str_ireplace(array_keys($bbtags), array_values($bbtags), $bbtext);

        $bbextended = array(
            "/\[url](.*?)\[\/url]/i" => "<a href=\"http://$1\" title=\"$1\">$1</a>",
            "/\[url=(.*?)\](.*?)\[\/url\]/i" => "<a href=\"$1\" title=\"$1\">$2</a>",
            "/\[email=(.*?)\](.*?)\[\/email\]/i" => "<a href=\"mailto:$1\">$2</a>",
            "/\[mail=(.*?)\](.*?)\[\/mail\]/i" => "<a href=\"mailto:$1\">$2</a>",
            "/\[img\]([^[]*)\[\/img\]/i" => "<img src=\"$1\" alt=\" \" />",
            "/\[image\]([^[]*)\[\/image\]/i" => "<img src=\"$1\" alt=\" \" />",
            "/\[image_left\]([^[]*)\[\/image_left\]/i" => "<img src=\"$1\" alt=\" \" class=\"img_left\" />",
            "/\[image_right\]([^[]*)\[\/image_right\]/i" => "<img src=\"$1\" alt=\" \" class=\"img_right\" />",
        );

        foreach($bbextended as $match=>$replacement){
            $bbtext = preg_replace($match, $replacement, $bbtext);
        }

        // Replace \n / \r by <p></p>
        $nl2pText = $this->nl2p($bbtext);

        return str_replace("<p>&nbsp;</p>", "<p></p>", $nl2pText);
    }

    /**
     * @param $str
     *
     * @return string
     */
    protected function nl2p($str)
    {
        $str = str_replace( array("\r\n", "\r"), "\n", $str );
        return "<p>" . str_replace( "\n", "</p><p>", $str ) . "</p>";
    }
}
