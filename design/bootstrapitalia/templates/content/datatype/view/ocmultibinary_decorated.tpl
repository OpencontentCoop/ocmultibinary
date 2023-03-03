{if $attribute.has_content}

	{def $groups = ocmultibinary_available_groups($attribute)}
	{if count($groups)|eq(0)}{set $groups = $groups|append('')}{/if}
	{def $groups_count = count($groups)}
	{if $groups_count|eq(1)}
		<div class="row mx-lg-n3">
			{foreach $groups as $group}
				{def $file_list = ocmultibinary_list_by_group($attribute, $group)}
				{if and($group|ne(''), $file_list|count()|gt(0))}
				<div class="col-12 px-lg-3">
					<h6 class="no_toc">{$group|wash()}</h6>
				</div>
				{/if}
				{foreach $file_list as $file}
				 <div class="col-md-6 px-lg-3 pb-lg-3">
					<div class="card card-teaser shadow p-4 mt-3 rounded border">
						{display_icon('it-clip', 'svg', 'icon')}
						<div class="card-body">
						  <h5 class="card-title">
							<a class="stretched-link" href={concat( 'ocmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>
								{if $file.display_name|ne('')}{$file.display_name|wash( xhtml )}{else}{$file.original_filename|clean_filename()|wash( xhtml )}{/if}
							</a>
						  {if $file.display_text|ne('')}
							  <small class="d-block my-2">{$file.display_text|wash( xhtml )}</small>
						  {/if}
							<small class="d-block">(File {$file.mime_type|explode('application/')|implode('')} {$file.filesize|si( byte )})</small>
						  </h5>
						</div>
					</div>
				</div>
				{/foreach}
				{undef $file_list}
			{/foreach}
		</div>
	{else}
		{ezscript_require('jquery.quicksearch.min.js')}
		{run-once}
		<script>{literal}
			$(document).ready(function (){
				$('.multibinary-search').each(function (){
					var searchInput = $(this).find('input');
					var containerId = $(this).find('.it-list').attr('id');
					searchInput.quicksearch('#'+containerId+ ' li');
				});
			})
		{/literal}</script>
		{/run-once}
		<div class="collapse-div collapse-left-icon my-4" role="tablist">
			{foreach $groups as $index => $group}
				{def $file_list = ocmultibinary_list_by_group($attribute, $group)}
				{if or($group|eq(''), $file_list|count()|eq(0))}{skip}{/if}
				<div class="collapse-header" id="heading-{$attribute.id}-{$index}">
					<button data-toggle="collapse" data-target="#collapse-{$attribute.id}-{$index}" aria-expanded="false" aria-controls="collapse-{$attribute.id}-{$index}">
						{$group|wash()}
					</button>
				</div>
				<div id="collapse-{$attribute.id}-{$index}" class="collapse" role="tabpanel" aria-labelledby="heading-{$attribute.id}-{$index}">
					<div class="collapse-body{if count($file_list)|gt(3)} multibinary-search{/if}">
						<div class="it-list-wrapper">
							{if count($file_list)|gt(3)}
							<div class="form-group mb-1">
								<div class="form-label-group">
									<input type="text"
										   class="form-control form-control-sm"
										   placeholder="{'Search in'|i18n('bootstrapitalia')} {$group|wash()}"
										   aria-invalid="false"/>
									<label class="d-none">
										{'Search in'|i18n('bootstrapitalia')} {$group|wash()}
									</label>
									<button class="autocomplete-icon btn btn-link" aria-label="{'Search'|i18n('openpa/search')}">
										{display_icon('it-search', 'svg', 'icon icon-sm')}
									</button>
								</div>
							</div>
							{/if}
							<ul class="it-list" id="list-{$attribute.id}-{$index}">
								{foreach $file_list as $file}
									<li>
										<a  class="text-decoration-none" href={concat( 'ocmultibinary/download/', $attribute.contentobject_id, '/', $attribute.id,'/', $attribute.version , '/', $file.filename ,'/file/', $file.original_filename|urlencode )|ezurl}>
											<div class="it-rounded-icon">
												{display_icon('it-clip', 'svg', 'icon')}
											</div>
											<div class="it-right-zone">
												<span class="text">
													{if $file.display_name|ne('')}{$file.display_name|wash( xhtml )}{else}{$file.original_filename|clean_filename()|wash( xhtml )}{/if}
													<em>
														File {$file.mime_type|explode('application/')|implode('')} {$file.filesize|si( byte )}
													{if $file.display_text|ne('')}
														<small class="d-block my-2">{$file.display_text|wash( xhtml )}</small>
													{/if}
													</em>
												</span>
											</div>
										</a>
									</li>
								{/foreach}
							</ul>
						</div>
					</div>
					{undef $file_list}
				</div>
			{/foreach}
		</div>
	{/if}

	{undef $groups $groups_count}
{/if}
