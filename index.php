<?php
require_once __DIR__ . '/layout.php';
global $title;
$title = $dir = $_ENV['WORK_DIR'];
$kanban = "$dir/.kanban";

if ($tasks = $_REQUEST['tasks'] ?? '')
    exit(file_put_contents($kanban, json_encode(['tasks' => json_decode($tasks)], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));

if (!file_exists($kanban))
    file_put_contents($kanban, '{"tasks": []}');

$tasks = json_decode(file_get_contents($kanban), TRUE);
?>

<div id="app" class="container py-3">
    <div class="row">
        <div v-for="type in types" class="col-sm-4" :style="{opacity: type === 'completed' ? 0.65 : 1}" :key="type">
            <div><input type="search" v-model.trim="search[type]" :placeholder="type + ' &#128269;'" style="font-size: 24px; border: 0;" class="heading"></div>

            <draggable v-model="filtered[type]" group="tasks" @change="rearrange" class="panel bg-light p-2">
                <div v-for="(task, index) in filtered[type]" :key="task.id" :class="!search[type] || task.text.indexOf(search[type]) !== -1 ? 'd-flex' : 'd-none'" class="flex-row align-items-center" :title="timeSince(task)">
                    <input type="checkbox" class="mr-2" v-model="task.type" :true-value="type === 'pending' ? 'processing' : 'completed'" :false-value="type === 'completed' ? 'pending' : type"/>
                    <div v-if="task.edit"><input type="text" v-model.lazy.trim="task.text" class="border-0  form-control form-control-sm" @keyup="e => keypress(task, e)" @blur="$delete(task, 'edit')" :ref="'edit'+task.id"></div>
                    <a v-else href="#" @click.prevent="$set(task, 'edit', true)">{{task.text}}</a>
                </div>
            </draggable>

            <hr/>

            <form @submit.prevent="addTask(type)" class="d-flex flex-row align-items-center">
                <input type="text" v-model="task" class="form-control mr-2" :placeholder="`${type} task..`" aria-describedby="new task">
                <button class="btn btn-primary" type="submit">Add</button>
            </form>
        </div>
    </div>
    <!--<pre class="debug pre-scrollable">tasks: {{tasks}}</pre>-->
</div>

<script>
    new Vue({
        el: '#app',
        data() {
            return {search: {}, tasks: <?=json_encode($tasks['tasks'] ?: []) ?>, task: '', filtered: null}
        },
        methods: {
            now() {
                return Math.floor(+(new Date()) / 1000);
            },
            addTask(type) {
                if (this.task !== '')
                    this.tasks.push({"text": this.task, priority: "medium", creation: this.now(), id: 'task' + Math.floor(Math.random() * 99999999999), type});

                this.task = '';
            },
            timeSince(task) {
                return timeago.format(task.creation * 1000);
            },
            rearrange() {
                let tasks = [];
                for (let type of this.types)
                    for (let task of this.filtered[type])
                        tasks.push(Object.assign(task, {type}));

                this.tasks = tasks;
            },
            keypress(task, e) {
                if (/^(Escape|Enter)$/.test(e.key))
                    this.$delete(task, 'edit');
            },
            categories() {
                return {pending: [], processing: [], completed: []};
            }
        },
        watch: {
            tasks: {
                deep: true,
                immediate: true,
                handler(tasks) {
                    this.filtered = this.categories();

                    for (let i = tasks.length - 1; i >= 0; i--) {
                        let task = tasks[i];
                        if (task.text) {
                            if (task.edit === true) setTimeout(() => this.$refs['edit' + task.id][0] ? this.$refs['edit' + task.id][0].focus() : '', 250);
                            this.filtered[task.type].unshift(task);
                        } else tasks.splice(i, 1);
                    }

                    clearTimeout(this.timeout);
                    this.timeout = setTimeout(() => fetch('', {method: "POST", body: new URLSearchParams("tasks=" + JSON.stringify(tasks))}), 250);
                },
            }
        },
        computed: {
            types() {
                return Object.keys(this.categories());
            },
        }
    });
</script>
