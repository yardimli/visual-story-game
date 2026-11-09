<section x-show="tab==='page'" class="space-y-5" aria-label="Story pages">
@foreach($story->pages as $p)
<article data-page-id="{{ $p->id }}" class="page-document rounded-xl border border-stone-200 p-4 dark:border-stone-700" :class="activePageId==={{ $p->id }}?'page-document-active':''" x-data="pageDocument(@js(['id'=>$p->id,'position'=>$p->position,'title'=>$p->title]))" @focusin="selectPage({{ $p->id }},{{ $p->position }})">
<header class="mb-3 flex flex-wrap items-center gap-2">
<span class="text-xs text-stone-500">Page {{ $p->position }}</span>
<input aria-label="Page {{ $p->position }} title" class="page-title min-w-0 flex-1 rounded border-stone-300 bg-transparent dark:border-stone-600" name="title" form="page-form-{{ $p->id }}" value="{{ $p->title }}" required maxlength="160" @input="changed({target:$el.form.querySelector('[name=narrative]')})">
@foreach(['up'=>'↑','down'=>'↓','remove'=>'Remove','insert'=>'Insert page after'] as $action=>$label)
<form method="POST" action="{{ route('pages.manage',$story) }}" @submit="submitAction($event)">@csrf<input type="hidden" name="revision" value="{{ $story->revision }}"><input type="hidden" name="page_id" value="{{ $p->id }}"><input type="hidden" name="action" value="{{ $action }}"><button class="btn-secondary page-action" aria-label="{{ ['up'=>'Move page up','down'=>'Move page down','remove'=>'Remove page','insert'=>'Insert blank page after'][$action] }}" @disabled(($action==='up' && $loop->parent->first)||($action==='down' && $loop->parent->last)||($action==='remove' && $story->pages->count()===1)||($action==='insert' && $story->pages->count()>=100))>{{ $label }}</button></form>
@endforeach
</header>
<form id="page-form-{{ $p->id }}" data-autosave :data-companion-form="activePageId==={{ $p->id }}?'page':null" @submit.prevent="flush()" action="{{ route('pages.update',[$story,$p]) }}" method="POST" class="page-document-columns">@csrf @method('PUT')<input type="hidden" name="revision" value="{{ $story->revision }}">
@foreach(['narrative'=>'Narrative overlay','interaction_plan'=>'Interaction plan'] as $field=>$label)
<div class="page-writing-column min-w-0"><p class="mb-2 text-sm font-semibold">{{ $label }}</p><textarea hidden name="{{ $field }}">{{ $p->$field }}</textarea><input type="hidden" name="{{ $field }}_delta" value="{{ json_encode($p->{$field.'_delta'}) }}"><div class="page-quill" data-editor="{{ $field }}" x-ignore></div></div>
@endforeach
</form>
</article>
@endforeach
</section>
