Venice Platform
===============

Venice AI platform bridge for Symfony AI. Supports chat completions (including streaming, tool
calling and the Venice-specific `venice_parameters`), embeddings, image generation, editing and
upscaling, text-to-speech, speech-to-text and asynchronous video generation.

Venice Documentation
--------------------

 * [API overview](https://docs.venice.ai/api-reference/api-spec)
 * [Chat completions (`/chat/completions`)](https://docs.venice.ai/api-reference/endpoint/chat/completions)
 * [Embeddings (`/embeddings`)](https://docs.venice.ai/api-reference/endpoint/embeddings/generate)
 * [Image generation (`/image/generate`)](https://docs.venice.ai/api-reference/endpoint/image/generate)
 * [Text-to-speech (`/audio/speech`)](https://docs.venice.ai/api-reference/endpoint/audio/speech)
 * [Speech-to-text (`/audio/transcriptions`)](https://docs.venice.ai/api-reference/endpoint/audio/transcriptions)
 * [Video generation (`/video/queue`)](https://docs.venice.ai/api-reference/endpoint/video/queue_generate)
 * [Model catalog (`/models`)](https://docs.venice.ai/api-reference/endpoint/models/list)

Test Fixtures
-------------

The test fixtures in `Tests/Fixtures/` contain binary media content with the following owners and licenses:

* `audio.mp3`: davidbain, Creative Commons, see [freesound.org](https://freesound.org/people/davidbain/sounds/136777/)

Resources
---------

 * [Contributing](https://symfony.com/doc/current/contributing/index.html)
 * [Report issues](https://github.com/symfony/ai/issues) and
   [send Pull Requests](https://github.com/symfony/ai/pulls)
   in the [main Symfony AI repository](https://github.com/symfony/ai)
